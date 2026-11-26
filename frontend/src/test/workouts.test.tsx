import { act, fireEvent, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { toast } from "sonner";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import { ApiError } from "@/lib/api";
import { fetchExercises } from "@/lib/exercise-api";
import {
  createWorkout,
  deleteWorkout,
  fetchWorkoutDiary,
  previewWorkout,
  updateWorkout,
  type Workout,
  type WorkoutDiary,
} from "@/lib/workout-api";
import { addIsoDays, startOfIsoWeek } from "@/lib/date";
import {
  exerciseListFixture,
  caloriePreviewFixture,
  cardioWorkoutFixture,
  runWorkoutFixture,
  workoutDiaryFixture,
  workoutFixture,
} from "@/test/workout-fixtures";
import { TEST_TODAY, TEST_WEEK_START } from "@/test/fixtures";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/workout-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/workout-api")>();

  return {
    ...actual,
    fetchWorkoutDiary: vi.fn(),
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
  toast: {
    success: vi.fn(),
    error: vi.fn(),
  },
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

function renderWorkouts(diary: WorkoutDiary = workoutDiaryFixture()) {
  return renderApp({
    user: completedUser,
    route: "/workouts",
    extraQueries: [[["workouts", "today"], diary]],
  });
}

async function replaceValue(
  user: TestUser,
  label: string | RegExp,
  value: string,
) {
  const input = screen.getByLabelText(label);
  await user.clear(input);
  await user.type(input, value);
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
}


async function selectExercise(user: TestUser, index: number, name: string) {
  const input = screen.getByLabelText(`Название упражнения ${index}`);
  await user.clear(input);
  await user.type(input, name.slice(0, Math.max(3, name.length - 1)));
  const option = await screen.findByRole("option", {
    name: new RegExp(name),
  });
  await user.click(option);
}

async function openStrengthEditor(user: TestUser) {
  await user.click(
    screen.getByRole("button", { name: "Быстрый старт: Силовая тренировка" }),
  );

  return screen.findByRole("dialog", { name: "Новая тренировка" });
}

async function fillValidStrength(
  user: TestUser,
  name = "Новая силовая",
) {
  await openStrengthEditor(user);
  await replaceValue(user, "Название тренировки", name);
  await replaceValue(user, "Длительность, мин", "55");
  await selectExercise(user, 1, "Приседания");
  await replaceValue(user, "Вес, кг", "80");
  await replaceValue(user, "Повторения", "5");
}

describe("workouts page", () => {
  beforeEach(() => {
    vi.mocked(fetchExercises).mockResolvedValue(exerciseListFixture());
    vi.mocked(fetchWorkoutDiary).mockResolvedValue(workoutDiaryFixture());
    vi.mocked(createWorkout).mockImplementation(async (input) =>
      workoutFixture({
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
      }),
    );
    vi.mocked(updateWorkout).mockImplementation(async (_id, input) =>
      workoutFixture({
        id: "workout-1",
        date: input.date,
        type: input.type,
        name: input.name,
        duration_seconds: input.duration_seconds,
      }),
    );
    vi.mocked(deleteWorkout).mockResolvedValue(undefined);
    vi.mocked(previewWorkout).mockResolvedValue(caloriePreviewFixture());
  });

  it("renders the training week, summary stats, filters and latest records", async () => {
    const user = userEvent.setup();
    renderWorkouts(
      workoutDiaryFixture({
        workouts: [
          workoutFixture(),
          runWorkoutFixture(),
          cardioWorkoutFixture(),
        ],
      }),
    );

    expect(
      screen.getByRole("heading", { name: "Дневник тренировок" }),
    ).toBeInTheDocument();
    expect(screen.getByText("13–19 января 2025")).toBeInTheDocument();
    const summary = screen.getByLabelText("Итоги тренировочной недели");
    expect(within(summary).getByText("3")).toBeInTheDocument();
    expect(within(summary).getByText("км бега за неделю")).toBeInTheDocument();
    expect(within(summary).getByText("5,2")).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Верх тела" })).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Пробежка" })).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Велосипед" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Силовые" }));
    expect(screen.getByRole("region", { name: "Верх тела" })).toBeInTheDocument();
    expect(screen.queryByRole("region", { name: "Пробежка" })).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Кардио" }));
    expect(screen.queryByRole("region", { name: "Верх тела" })).not.toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Пробежка" })).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Велосипед" })).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Все" }));
    expect(screen.getByRole("region", { name: "Верх тела" })).toBeInTheDocument();
  });

  it("shows the filtered empty state and opens the matching quick start", async () => {
    const user = userEvent.setup();
    renderWorkouts(workoutDiaryFixture({ workouts: [], recent: [] }));

    expect(
      screen.getByText("Место для твоих достижений"),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Здесь появится история твоих тренировок."),
    ).toBeInTheDocument();

    await user.click(
      screen.getByRole("button", { name: "Быстрый старт: Пробежка" }),
    );
    expect(
      screen.getByRole("dialog", { name: "Новая тренировка" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Название тренировки")).toHaveValue("Пробежка");
    expect(
      screen.getByRole("button", { name: "Бег", pressed: true }),
    ).toBeInTheDocument();
  });

  it("closes the editor on Escape and returns focus to the opener", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    const opener = screen.getByRole("button", {
      name: "Быстрый старт: Силовая тренировка",
    });
    await user.click(opener);

    const dialog = await screen.findByRole("dialog", {
      name: "Новая тренировка",
    });
    expect(dialog).toContainElement(document.activeElement as HTMLElement);

    await user.keyboard("{Escape}");
    expect(
      screen.queryByRole("dialog", { name: "Новая тренировка" }),
    ).not.toBeInTheDocument();
    expect(opener).toHaveFocus();
  });

  it("creates a strength workout with nested exercises and sets", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await fillValidStrength(user);

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => {
      expect(createWorkout).toHaveBeenCalledTimes(1);
    });

    expect(createWorkout).toHaveBeenCalledWith({
      type: "strength",
      date: TEST_TODAY,
      name: "Новая силовая",
      duration_seconds: 3300,
      calories_mode: "auto",
      feeling: null,
      note: null,
      exercises: [
        {
          exercise_id: "catalog-squat",
          name: "Приседания",
          mode: "weighted",
          sets: [{ weight_kg: 80, reps: 5 }],
        },
      ],
    });

    await waitFor(() => {
      expect(
        screen.queryByRole("dialog", { name: "Новая тренировка" }),
      ).not.toBeInTheDocument();
    });
  });

  it("adds and removes dynamic exercises and sets", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrengthEditor(user);

    await user.click(
      screen.getByRole("button", { name: "＋ Добавить упражнение" }),
    );
    expect(screen.getByLabelText("Название упражнения 2")).toBeInTheDocument();

    await user.click(screen.getAllByRole("button", { name: "＋ Подход" })[0]);
    expect(screen.getAllByLabelText("Повторения")).toHaveLength(3);

    await user.click(screen.getAllByRole("button", { name: "Удалить подход 2" })[0]);
    expect(screen.getAllByLabelText("Повторения")).toHaveLength(2);

    await user.click(
      screen.getByRole("button", { name: "Удалить упражнение 2" }),
    );
    expect(
      screen.queryByLabelText("Название упражнения 2"),
    ).not.toBeInTheDocument();
  });

  it("switches accounting mode and sends time or reps only", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrengthEditor(user);

    await selectExercise(user, 1, "Планка");
    await user.selectOptions(
      screen.getByLabelText("Способ учёта упражнения 1"),
      "time",
    );
    await replaceValue(user, "Время, сек", "60");
    await replaceValue(user, "Длительность, мин", "10");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0].exercises).toEqual([
      { exercise_id: "catalog-plank", name: "Планка", mode: "time", sets: [{ seconds: 60 }] },
    ]);
  });

  it("creates a run from speed and leaves distance to the server", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      screen.getByRole("button", { name: "Быстрый старт: Пробежка" }),
    );
    const dialog = await screen.findByRole("dialog", { name: "Новая тренировка" });

    await replaceValue(user, "Название тренировки", "Интервалы");
    await replaceValue(user, "Длительность, мин", "30");
    await user.selectOptions(
      within(dialog).getByLabelText("Как записать кардио"),
      "speed",
    );
    await replaceValue(user, "Скорость, км/ч", "10");
    await replaceValue(user, "Средний пульс, уд/мин", "150");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0]).toMatchObject({
      type: "run",
      name: "Интервалы",
      duration_seconds: 1800,
      input_mode: "speed",
      speed_kmh: 10,
      pulse: 150,
      incline_percent: null,
    });
    expect(vi.mocked(createWorkout).mock.calls[0][0].distance_km).toBeUndefined();
  });

  it("shows only pulse (no incline) for other cardio", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      screen.getByRole("button", { name: "Быстрый старт: Другое кардио" }),
    );

    expect(screen.getByLabelText("Средний пульс, уд/мин")).toBeInTheDocument();
    expect(screen.queryByLabelText("Уклон дорожки, %")).not.toBeInTheDocument();
  });

  it("reveals saved exercise sets and cardio metrics in the details view", async () => {
    const user = userEvent.setup();
    renderWorkouts(
      workoutDiaryFixture({
        workouts: [workoutFixture(), runWorkoutFixture()],
      }),
    );

    const strength = screen.getByRole("region", { name: "Верх тела" });
    await user.click(
      within(strength).getByRole("button", { name: "Подробнее" }),
    );
    expect(within(strength).getByText(/60 кг/)).toBeInTheDocument();
    expect(within(strength).getByText("Планка", { selector: "strong" })).toBeInTheDocument();
    expect(within(strength).getByText("Самочувствие: Комфортно")).toBeInTheDocument();

    const run = screen.getByRole("region", { name: "Пробежка" });
    await user.click(within(run).getByRole("button", { name: "Подробнее" }));
    expect(within(run).getByText(/Скорость: 9,9 км\/ч/)).toBeInTheDocument();
    expect(within(run).getByText(/Пульс: 145/)).toBeInTheDocument();
    expect(within(run).getByText(/Уклон: 2,5%/)).toBeInTheDocument();
  });

  it("edits a workout, moves its date and invalidates old and new day/week caches", async () => {
    const user = userEvent.setup();
    const { queryClient } = renderWorkouts();
    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");
    const nextDate = addIsoDays(TEST_TODAY, 1);

    await user.click(
      within(screen.getByRole("region", { name: "Верх тела" })).getByRole(
        "button",
        { name: "Изменить" },
      ),
    );
    expect(
      screen.getByRole("dialog", { name: "Редактировать тренировку" }),
    ).toBeInTheDocument();

    await replaceValue(user, "Название тренировки", "Верх тела — обновлено");
    fireEvent.change(screen.getByLabelText("Дата"), {
      target: { value: nextDate },
    });
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(updateWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(updateWorkout).mock.calls[0][0]).toBe("workout-1");
    expect(vi.mocked(updateWorkout).mock.calls[0][1]).toMatchObject({
      date: nextDate,
      name: "Верх тела — обновлено",
      type: "strength",
    });

    await waitFor(() => {
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["workouts"],
      });
    });
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ["dashboard", TEST_TODAY],
    });
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ["dashboard", nextDate],
    });
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ["weekly", startOfIsoWeek(TEST_TODAY)],
    });
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ["weekly", startOfIsoWeek(nextDate)],
    });
  });

  it("repeats a workout on the selected date without editing the original", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      within(screen.getByRole("region", { name: "Верх тела" })).getByRole(
        "button",
        { name: "Повторить" },
      ),
    );

    expect(
      screen.getByRole("dialog", { name: "Повторить тренировку" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Название тренировки")).toHaveValue("Верх тела");
    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0]).toMatchObject({
      type: "strength",
      date: TEST_TODAY,
      name: "Верх тела",
    });
  });

  it("deletes a workout and keeps the card until the server confirms", async () => {
    const user = userEvent.setup();
    const pending = deferred<void>();
    vi.mocked(deleteWorkout).mockReturnValue(pending.promise);
    const { queryClient } = renderWorkouts();
    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");

    const card = screen.getByRole("region", { name: "Верх тела" });
    await user.click(within(card).getByRole("button", { name: "Удалить" }));
    expect(deleteWorkout).toHaveBeenCalledWith("workout-1");
    expect(within(card).getByRole("button", { name: "Удаляем…" })).toBeDisabled();

    pending.resolve();
    await waitFor(() => {
      expect(within(card).queryByRole("button", { name: "Удаляем…" })).not.toBeInTheDocument();
    });
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["workouts"] });
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ["dashboard", TEST_TODAY],
    });
    expect(invalidateSpy).toHaveBeenCalledWith({
      queryKey: ["weekly", startOfIsoWeek(TEST_TODAY)],
    });
  });

  it("keeps the undo toast action bound to the session that deleted the workout", async () => {
    const user = userEvent.setup();
    const { queryClient } = renderWorkouts();

    const card = screen.getByRole("region", { name: "Верх тела" });
    await user.click(within(card).getByRole("button", { name: "Удалить" }));

    await waitFor(() => {
      expect(toast.success).toHaveBeenCalledWith(
        "Тренировка удалена",
        expect.anything(),
      );
    });

    const deleteToast = vi
      .mocked(toast.success)
      .mock.calls.find(([message]) => message === "Тренировка удалена");
    const action = deleteToast?.[1]?.action;

    expect(action).toBeDefined();
    if (
      !action ||
      typeof action !== "object" ||
      action === null ||
      !("onClick" in action)
    ) {
      return;
    }

    const undo = action.onClick as () => void;

    queryClient.setQueryData(["session"], {
      ...completedUser,
      id: 3,
      email: "another@example.com",
    });
    await act(async () => {
      undo();
    });
    expect(createWorkout).not.toHaveBeenCalled();

    queryClient.setQueryData(["session"], null);
    await act(async () => {
      undo();
    });
    expect(createWorkout).not.toHaveBeenCalled();
  });

  it("does not show a late delete success after the session changes", async () => {
    const user = userEvent.setup();
    const pending = deferred<void>();
    vi.mocked(deleteWorkout).mockReturnValue(pending.promise);
    const { queryClient } = renderWorkouts();

    const card = screen.getByRole("region", { name: "Верх тела" });
    await user.click(within(card).getByRole("button", { name: "Удалить" }));
    expect(deleteWorkout).toHaveBeenCalledWith("workout-1");

    queryClient.setQueryData(["session"], {
      ...completedUser,
      id: 3,
      email: "another@example.com",
    });

    await act(async () => {
      pending.resolve();
    });

    expect(toast.success).not.toHaveBeenCalled();
    expect(createWorkout).not.toHaveBeenCalled();
  });

  it("keeps the dialog open and maps nested server validation errors", async () => {
    const user = userEvent.setup();
    vi.mocked(createWorkout).mockRejectedValueOnce(
      new ApiError(422, "Проверьте поля.", {
        message: "Проверьте поля.",
        errors: {
          "exercises.0.sets.0.reps": "Укажи повторения.",
        },
      }),
    );
    renderWorkouts();
    await fillValidStrength(user, "Сохранённое имя");

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );

    await waitFor(() => {
      expect(screen.getByText("Подход 1: Укажи повторения.")).toBeInTheDocument();
    });
    expect(
      screen.getByRole("dialog", { name: "Новая тренировка" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Название тренировки")).toHaveValue(
      "Сохранённое имя",
    );
  });

  it("blocks duplicate submissions while the first request is pending", async () => {
    const user = userEvent.setup();
    const pending = deferred<Workout>();
    vi.mocked(createWorkout).mockReturnValue(pending.promise);
    renderWorkouts();
    await fillValidStrength(user);

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );
    expect(
      screen.getByRole("button", { name: "Сохраняем…" }),
    ).toBeDisabled();
    await user.click(screen.getByRole("button", { name: "Сохраняем…" }));
    expect(createWorkout).toHaveBeenCalledTimes(1);

    pending.resolve(workoutFixture({ name: "Новая силовая" }));
    await waitFor(() => {
      expect(
        screen.queryByRole("dialog", { name: "Новая тренировка" }),
      ).not.toBeInTheDocument();
    });
  });

  it("does not apply a late save response after the session changes", async () => {
    const user = userEvent.setup();
    const pending = deferred<Workout>();
    vi.mocked(createWorkout).mockReturnValue(pending.promise);
    const { queryClient } = renderWorkouts();
    await fillValidStrength(user);

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );
    expect(
      screen.getByRole("button", { name: "Сохраняем…" }),
    ).toBeDisabled();

    queryClient.setQueryData(["session"], {
      ...completedUser,
      id: 3,
      email: "another@example.com",
    });

    await act(async () => {
      pending.resolve(
        workoutFixture({
          date: addIsoDays(TEST_TODAY, 1),
          name: "Поздний ответ старой сессии",
        }),
      );
    });

    expect(toast.success).not.toHaveBeenCalled();
    expect(screen.getByLabelText("Выбрать день")).toHaveValue(TEST_TODAY);
    expect(
      screen.getByRole("dialog", { name: "Новая тренировка" }),
    ).toBeInTheDocument();
  });

  it("does not let a late response change the date after the user switched days", async () => {
    const user = userEvent.setup();
    const pending = deferred<Workout>();
    vi.mocked(createWorkout).mockReturnValue(pending.promise);
    renderWorkouts();
    await fillValidStrength(user);

    await user.click(
      screen.getByRole("button", { name: "Сохранить тренировку" }),
    );
    await user.click(screen.getByRole("button", { name: /16:/ }));

    await waitFor(() => {
      expect(
        screen.queryByRole("dialog", { name: "Новая тренировка" }),
      ).not.toBeInTheDocument();
    });
    expect(screen.getByLabelText("Выбрать день")).toHaveValue(
      addIsoDays(TEST_TODAY, 1),
    );

    pending.resolve(
      workoutFixture({ date: TEST_TODAY, name: "Поздний ответ" }),
    );
    await waitFor(() => {
      expect(screen.getByLabelText("Выбрать день")).toHaveValue(
        addIsoDays(TEST_TODAY, 1),
      );
    });
    expect(
      screen.queryByRole("dialog", { name: "Новая тренировка" }),
    ).not.toBeInTheDocument();
  });

  it("navigates to the next/previous week and back to the fresh server today", async () => {
    const user = userEvent.setup();
    renderWorkouts();

    await user.click(screen.getByRole("button", { name: "Следующая неделя" }));
    expect(screen.getByLabelText("Выбрать день")).toHaveValue(
      addIsoDays(TEST_TODAY, 7),
    );
    expect(fetchWorkoutDiary).toHaveBeenCalledWith(addIsoDays(TEST_TODAY, 7));

    await user.click(screen.getByRole("button", { name: "Предыдущая неделя" }));
    expect(screen.getByLabelText("Выбрать день")).toHaveValue(TEST_TODAY);

    vi.mocked(fetchWorkoutDiary).mockResolvedValueOnce(
      workoutDiaryFixture({ today: addIsoDays(TEST_TODAY, 1) }),
    );
    await user.click(screen.getByRole("button", { name: "Сегодня" }));
    await waitFor(() => {
      expect(screen.getByLabelText("Выбрать день")).toHaveValue(
        addIsoDays(TEST_TODAY, 1),
      );
    });
  });

  it("shows a loading state and a retryable error state", async () => {
    const pending = deferred<WorkoutDiary>();
    vi.mocked(fetchWorkoutDiary).mockReturnValueOnce(pending.promise);
    renderApp({ user: completedUser, route: "/workouts" });

    await waitFor(() => {
      expect(
        screen.getByLabelText("Загрузка тренировок"),
      ).toBeInTheDocument();
    });

    pending.resolve(workoutDiaryFixture());
    await waitFor(() => {
      expect(screen.getByRole("region", { name: "Верх тела" })).toBeInTheDocument();
    });
  });

  it("retries a failed diary request", async () => {
    const user = userEvent.setup();
    vi.mocked(fetchWorkoutDiary).mockRejectedValueOnce(
      new ApiError(500, "Сервер недоступен."),
    );
    renderApp({ user: completedUser, route: "/workouts" });

    await waitFor(() => {
      expect(screen.getByText(/Не удалось загрузить тренировки/)).toBeInTheDocument();
    });

    vi.mocked(fetchWorkoutDiary).mockResolvedValue(workoutDiaryFixture());
    await user.click(screen.getByRole("button", { name: "Повторить" }));
    await waitFor(() => {
      expect(fetchWorkoutDiary).toHaveBeenCalledTimes(2);
    });
    await waitFor(() => {
      expect(screen.getByRole("region", { name: "Верх тела" })).toBeInTheDocument();
    });
  });

  it("navigates from the dashboard sidebar and the mobile switch", async () => {
    const user = userEvent.setup();
    renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["workouts", "today"], workoutDiaryFixture()]],
    });

    await user.click(screen.getByRole("button", { name: "Тренировки" }));
    expect(
      screen.getByRole("heading", { name: "Дневник тренировок" }),
    ).toBeInTheDocument();

    await user.click(
      screen.getByRole("button", { name: "Мой день — мобильная навигация" }),
    );
    expect(
      screen.getByRole("heading", { name: "Хороший день для себя ☀" }),
    ).toBeInTheDocument();

    await user.click(
      screen.getByRole("button", { name: "Тренировки — мобильная навигация" }),
    );
    expect(
      screen.getByRole("heading", { name: "Дневник тренировок" }),
    ).toBeInTheDocument();
  });

  it("opens the workout diary from the activity card", async () => {
    const user = userEvent.setup();
    renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["workouts", "today"], workoutDiaryFixture()]],
    });

    await user.click(
      screen.getByRole("button", { name: "Дневник тренировок" }),
    );
    expect(
      screen.getByRole("heading", { name: "Дневник тренировок" }),
    ).toBeInTheDocument();
  });

  it("keeps the current week start stable at the Monday boundary", async () => {
    expect(TEST_WEEK_START).toBe("2025-01-13");
    expect(startOfIsoWeek("2025-01-19")).toBe(TEST_WEEK_START);
    expect(startOfIsoWeek("2025-01-20")).toBe("2025-01-20");
  });
});
