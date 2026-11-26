import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import {
  createWorkout,
  fetchWorkout,
  fetchWorkoutDiary,
  previewWorkout,
  updateWorkout,
  type Workout,
} from "@/lib/workout-api";
import { createExercise, fetchExercises } from "@/lib/exercise-api";
import {
  caloriePreviewFixture,
  exerciseListFixture,
  workoutDiaryFixture,
  workoutFixture,
} from "@/test/workout-fixtures";
import { TEST_TODAY } from "@/test/fixtures";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/workout-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/workout-api")>();
  return {
    ...actual,
    fetchWorkoutDiary: vi.fn(),
    fetchWorkout: vi.fn(),
    createWorkout: vi.fn(),
    updateWorkout: vi.fn(),
    deleteWorkout: vi.fn(),
    previewWorkout: vi.fn(),
  };
});

vi.mock("@/lib/exercise-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/exercise-api")>();
  return {
    ...actual,
    fetchExercises: vi.fn(),
    createExercise: vi.fn(),
  };
});

vi.mock("sonner", () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const completedUser: SessionUser = {
  id: 2,
  email: "completed@example.com",
  name: null,
  profile_completed: true,
  profile: {
    weight_kg: 80,
    height_cm: 180,
    birth_date: "1990-01-15",
    fat_percentage: null,
    gender: "male",
    activity_level: "moderate",
    goal: "lose_weight",
    timezone: "Europe/Moscow",
  },
};

type TestUser = ReturnType<typeof userEvent.setup>;

function renderWorkouts(diary = workoutDiaryFixture()) {
  return renderApp({
    user: completedUser,
    route: "/workouts",
    extraQueries: [[["workouts", "today"], diary]],
  });
}

async function replaceValue(user: TestUser, label: string | RegExp, value: string) {
  const input = screen.getByLabelText(label);
  await user.clear(input);
  await user.type(input, value);
}

async function openStrength(user: TestUser) {
  await user.click(
    screen.getByRole("button", { name: "Быстрый старт: Силовая тренировка" }),
  );

  return screen.findByRole("dialog", { name: "Новая тренировка" });
}

function mapCreated(input: Parameters<typeof createWorkout>[0]): Workout {
  return workoutFixture({
    id: "created-workout",
    date: input.date,
    type: input.type,
    name: input.name,
    duration_seconds: input.duration_seconds,
    exercises:
      input.type === "strength"
        ? (input.exercises ?? []).map((exercise, index) => ({
            id: `created-exercise-${index}`,
            exercise_id: exercise.exercise_id ?? null,
            name: exercise.name,
            mode: exercise.mode,
            position: index,
            sets: exercise.sets.map((set, setIndex) => ({
              id: `created-set-${index}-${setIndex}`,
              position: setIndex,
              weight_kg: set.weight_kg ?? null,
              reps: set.reps ?? null,
              seconds: set.seconds ?? null,
            })),
          }))
        : [],
  });
}

describe("exercise catalog picker", () => {
  beforeEach(() => {
    vi.mocked(fetchExercises).mockResolvedValue(exerciseListFixture());
    vi.mocked(fetchWorkoutDiary).mockResolvedValue(workoutDiaryFixture());
    vi.mocked(previewWorkout).mockResolvedValue(caloriePreviewFixture());
    vi.mocked(createWorkout).mockImplementation(async (input) => mapCreated(input));
    vi.mocked(updateWorkout).mockImplementation(async (_id, input) =>
      workoutFixture({
        id: "workout-1",
        date: input.date,
        type: input.type,
        name: input.name,
        duration_seconds: input.duration_seconds,
      }),
    );
    vi.mocked(createExercise).mockResolvedValue({
      id: "custom-1",
      name: "Тяга к поясу",
      muscle_group: "Спина",
      equipment: "Гантели",
      measurement_type: "reps",
      is_custom: true,
    });
  });

  it("searches, filters by muscle and equipment, and links the chosen exercise id", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrength(user);

    const input = screen.getByLabelText("Название упражнения 1");
    await user.type(input, "жим");

    expect(
      await screen.findByRole("option", { name: /Жим лёжа/ }),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("option", { name: /Приседания/ }),
    ).not.toBeInTheDocument();

    await user.selectOptions(
      screen.getByLabelText("Группа мышц упражнения 1"),
      "Грудь",
    );
    await user.selectOptions(
      screen.getByLabelText("Оборудование упражнения 1"),
      "Штанга",
    );

    await user.click(screen.getByRole("option", { name: /Жим лёжа/ }));

    expect(input).toHaveValue("Жим лёжа");
    expect(screen.getByText(/Справочник: Жим лёжа/)).toBeInTheDocument();

    await replaceValue(user, "Длительность, мин", "45");
    await replaceValue(user, "Вес, кг", "80");
    await replaceValue(user, "Повторения", "5");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0].exercises?.[0]).toMatchObject({
      exercise_id: "catalog-bench",
      name: "Жим лёжа",
      mode: "weighted",
    });
  });

  it("supports keyboard-only selection and switches the accounting mode safely for a new exercise", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrength(user);

    const input = screen.getByLabelText("Название упражнения 1");
    await user.type(input, "План");
    await user.keyboard("{ArrowDown}{Enter}");

    expect(input).toHaveValue("Планка");
    expect(screen.getByLabelText("Способ учёта упражнения 1")).toHaveValue(
      "time",
    );
    expect(screen.getByLabelText("Время, сек")).toBeInTheDocument();

    await replaceValue(user, "Длительность, мин", "10");
    await replaceValue(user, "Время, сек", "60");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0].exercises?.[0]).toEqual({
      exercise_id: "catalog-plank",
      name: "Планка",
      mode: "time",
      sets: [{ seconds: 60 }],
    });
  });

  it("creates a personal exercise and selects it immediately", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrength(user);

    await user.click(
      screen.getByRole("button", { name: "＋ Создать личное упражнение" }),
    );
    await user.type(screen.getByLabelText("Название"), "Тяга к поясу");
    await user.selectOptions(screen.getByLabelText("Группа мышц"), "Спина");
    await user.selectOptions(screen.getByLabelText("Оборудование"), "Гантели");
    await user.selectOptions(
      screen.getByLabelText("Способ учёта"),
      "reps",
    );
    await user.click(
      screen.getByRole("button", { name: "Создать и выбрать" }),
    );

    await waitFor(() => {
      expect(createExercise).toHaveBeenCalledWith({
        name: "Тяга к поясу",
        muscle_group: "Спина",
        equipment: "Гантели",
        measurement_type: "reps",
      });
    });

    expect(screen.getByLabelText("Название упражнения 1")).toHaveValue(
      "Тяга к поясу",
    );
    expect(
      screen.getByLabelText("Способ учёта упражнения 1"),
    ).toHaveValue("reps");

    await replaceValue(user, "Длительность, мин", "20");
    await replaceValue(user, "Повторения", "12");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0].exercises?.[0]).toMatchObject({
      exercise_id: "custom-1",
      name: "Тяга к поясу",
      mode: "reps",
    });
  });

  it("blocks an incompatible legacy link but preserves sets, then links manually", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      within(screen.getByRole("region", { name: "Верх тела" })).getByRole(
        "button",
        { name: "Изменить" },
      ),
    );

    const dialog = await screen.findByRole("dialog", {
      name: "Редактировать тренировку",
    });
    expect(
      within(dialog).getAllByText(/Свободное название сохранено/).length,
    ).toBeGreaterThan(0);

    const input = screen.getByLabelText("Название упражнения 1");
    await user.clear(input);
    await user.type(input, "План");
    await user.click(screen.getByRole("option", { name: /Планка/ }));

    expect(
      screen.getByText(/Способ учёта не совпадает с этим упражнением/),
    ).toBeInTheDocument();
    expect(input).toHaveValue("Жим лёжа");
    expect(screen.getAllByLabelText("Вес, кг")[0]).toHaveValue("60");
    expect(screen.getAllByLabelText("Повторения")[0]).toHaveValue("10");

    await user.clear(input);
    await user.type(input, "Жи");
    await user.click(screen.getByRole("option", { name: /Жим лёжа/ }));
    expect(screen.getByText(/Справочник: Жим лёжа/)).toBeInTheDocument();

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(updateWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(updateWorkout).mock.calls[0][1].exercises?.[0]).toMatchObject({
      exercise_id: "catalog-bench",
      name: "Жим лёжа",
      mode: "weighted",
      sets: [
        { weight_kg: 60, reps: 10 },
        { weight_kg: 62.5, reps: 8 },
      ],
    });
  });

  it("does not link or rewrite a legacy snapshot from typed search text alone", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      within(screen.getByRole("region", { name: "Верх тела" })).getByRole(
        "button",
        { name: "Изменить" },
      ),
    );

    await screen.findByRole("dialog", { name: "Редактировать тренировку" });
    const input = screen.getByLabelText("Название упражнения 1");
    expect(input).toHaveValue("Жим лёжа");

    await user.clear(input);
    await user.type(input, "Жим лёжа");
    expect(
      screen.queryByText(/Справочник: Жим лёжа/),
    ).not.toBeInTheDocument();
    expect(
      screen.getAllByText(/Свободное название сохранено/).length,
    ).toBeGreaterThan(0);

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(updateWorkout).toHaveBeenCalledTimes(1));
    const exercise = vi.mocked(updateWorkout).mock.calls[0][1].exercises?.[0];
    expect(exercise).toMatchObject({
      name: "Жим лёжа",
      mode: "weighted",
      sets: [
        { weight_kg: 60, reps: 10 },
        { weight_kg: 62.5, reps: 8 },
      ],
    });
    expect(exercise?.exercise_id).toBeUndefined();
  });

  it("keeps the catalog id when repeating a workout", async () => {
    const user = userEvent.setup();
    const linked = workoutFixture({
      exercises: [
        {
          id: "exercise-1",
          exercise_id: "catalog-bench",
          name: "Жим лёжа",
          mode: "weighted",
          position: 0,
          sets: [
            {
              id: "set-1",
              position: 0,
              weight_kg: 60,
              reps: 10,
              seconds: null,
            },
          ],
        },
      ],
    });

    vi.mocked(fetchWorkoutDiary).mockResolvedValue(
      workoutDiaryFixture({ workouts: [linked], recent: [linked] }),
    );
    renderWorkouts(workoutDiaryFixture({ workouts: [linked], recent: [linked] }));

    await user.click(
      within(screen.getByRole("region", { name: "Верх тела" })).getByRole(
        "button",
        { name: "Повторить" },
      ),
    );
    expect(screen.getByLabelText("Название упражнения 1")).toHaveValue(
      "Жим лёжа",
    );
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0].exercises?.[0]).toMatchObject({
      exercise_id: "catalog-bench",
      name: "Жим лёжа",
      mode: "weighted",
      sets: [{ weight_kg: 60, reps: 10 }],
    });
  });

  it("opens a workout from a /workouts?workout= deep link", async () => {
    vi.mocked(fetchWorkout).mockResolvedValue(workoutFixture());

    renderApp({
      user: completedUser,
      route: "/workouts?workout=workout-1",
      extraQueries: [[["workouts", "today"], workoutDiaryFixture()]],
    });

    expect(
      await screen.findByRole("dialog", { name: "Редактировать тренировку" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Название тренировки")).toHaveValue(
      "Верх тела",
    );
    expect(TEST_TODAY).toBe("2025-01-15");
  });
  it("requires a catalog choice for a new exercise instead of a free name", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrength(user);

    await replaceValue(user, "Длительность, мин", "45");
    await replaceValue(user, "Вес, кг", "80");
    await replaceValue(user, "Повторения", "5");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    expect(
      await screen.findByText("Выбери упражнение из справочника или создай личное."),
    ).toBeInTheDocument();
    expect(createWorkout).not.toHaveBeenCalled();
  });

});
