import { act, fireEvent, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { toast } from "sonner";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import {
  createWorkout,
  deleteWorkout,
  fetchWorkoutDiary,
  previewWorkout,
  updateWorkout,
  type Workout,
  type WorkoutCaloriePreview,
} from "@/lib/workout-api";
import { addIsoDays } from "@/lib/date";
import { fetchExercises } from "@/lib/exercise-api";
import {
  exerciseListFixture,
  calorieExplanationFixture,
  caloriePreviewFixture,
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

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });

  return { promise, resolve, reject };
}

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

async function selectExercise(user: TestUser, index: number, name: string) {
  const input = screen.getByLabelText(`Название упражнения ${index}`);
  await user.clear(input);
  await user.type(input, name.slice(0, Math.max(3, name.length - 1)));
  const option = await screen.findByRole("option", {
    name: new RegExp(name),
  });
  await user.click(option);
}

async function openStrength(user: TestUser) {
  await user.click(
    screen.getByRole("button", { name: "Быстрый старт: Силовая тренировка" }),
  );

  return screen.findByRole("dialog", { name: "Новая тренировка" });
}

async function fillStrength(user: TestUser) {
  await openStrength(user);
  await replaceValue(user, "Длительность, мин", "45");
  await selectExercise(user, 1, "Приседания");
  await replaceValue(user, "Вес, кг", "80");
  await replaceValue(user, "Повторения", "5");
}

function autoWorkout(overrides: Partial<Workout> = {}): Workout {
  return workoutFixture({
    calories_mode: "auto",
    calories_status: "calculated",
    calories: 150,
    active_kcal: 150,
    gross_kcal: 210,
    calorie_method: "strength_character",
    met_code: "02054",
    met_value: 3.5,
    met_label: "Силовая, обычная нагрузка",
    weight_kg: 80,
    weight_source: "goal_snapshot",
    catalog_version: "2024/v1",
    formula_version: "met-1-v1",
    calorie_explanation: calorieExplanationFixture(),
    strength_character: "ordinary",
    ...overrides,
  });
}

describe("automatic workout calories", () => {
  beforeEach(() => {
    vi.mocked(fetchExercises).mockResolvedValue(exerciseListFixture());
    vi.mocked(fetchWorkoutDiary).mockResolvedValue(workoutDiaryFixture());
    vi.mocked(previewWorkout).mockResolvedValue(caloriePreviewFixture());
    vi.mocked(createWorkout).mockImplementation(async (input) =>
      workoutFixture({
        id: "created-workout",
        date: input.date,
        type: input.type,
        name: input.name,
        duration_seconds: input.duration_seconds,
        calories: input.calories_mode === "manual" ? input.calories ?? null : 150,
        calories_mode: input.calories_mode ?? "manual",
        calories_status: input.calories_mode === "manual" ? "manual" : "calculated",
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
      autoWorkout({
        date: input.date,
        type: input.type,
        name: input.name,
        duration_seconds: input.duration_seconds,
      }),
    );
    vi.mocked(deleteWorkout).mockResolvedValue(undefined);
  });

  it("defaults to automatic, keeps a manual value through mode switches, and saves it explicitly", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await openStrength(user);

    expect(
      screen.getByRole("radio", { name: "Рассчитать автоматически" }),
    ).toHaveAttribute("aria-checked", "true");

    await user.click(screen.getByRole("radio", { name: "Указать вручную" }));
    await replaceValue(user, "Калории активности", "321");
    await user.click(screen.getByRole("radio", { name: "Рассчитать автоматически" }));
    expect(
      screen.queryByLabelText("Калории активности"),
    ).not.toBeInTheDocument();
    await user.click(screen.getByRole("radio", { name: "Указать вручную" }));
    expect(screen.getByLabelText("Калории активности")).toHaveValue("321");

    await replaceValue(user, "Длительность, мин", "45");
    await selectExercise(user, 1, "Приседания");
    await replaceValue(user, "Вес, кг", "80");
    await replaceValue(user, "Повторения", "5");
    await user.click(screen.getByRole("button", { name: "Сохранить тренировку" }));

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0]).toMatchObject({
      calories_mode: "manual",
      calories: 321,
    });
  });

  it("renders the server preview, explanation, active and gross values", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await fillStrength(user);
    await user.selectOptions(
      screen.getByLabelText("Характер силовой тренировки"),
      "ordinary",
    );

    await waitFor(() => {
      expect(previewWorkout).toHaveBeenCalled();
      expect(screen.getByText(/≈ 150 ккал активности/)).toBeInTheDocument();
    });

    const panel = screen.getByLabelText("Предпросмотр расчёта калорий");
    expect(
      within(panel).getAllByText(/≈ 210 ккал всего/).length,
    ).toBeGreaterThan(0);
    expect(within(panel).getAllByText(/MET 3,5/).length).toBeGreaterThan(0);
    expect(
      within(panel).getAllByText(/Силовая, обычная нагрузка/).length,
    ).toBeGreaterThan(0);
    expect(within(panel).getAllByText(/вес 80 кг/).length).toBeGreaterThan(0);

    await user.click(within(panel).getByText("Как рассчитано"));
    expect(
      within(panel).getByText(/Расход сверх условного покоя/),
    ).toBeInTheDocument();
    expect(
      within(panel).getByRole("link", { name: "Справочник MET" }),
    ).toHaveAttribute(
      "href",
      "https://pacompendium.com/conditioning-exercise/",
    );
  });

  it("applies only the latest preview response after the date changes", async () => {
    const user = userEvent.setup();
    const first = deferred<WorkoutCaloriePreview>();
    const second = deferred<WorkoutCaloriePreview>();
    const nextDate = addIsoDays(TEST_TODAY, 1);

    vi.mocked(previewWorkout).mockImplementation((input) =>
      input.date === nextDate ? second.promise : first.promise,
    );

    renderWorkouts();
    await fillStrength(user);

    await waitFor(() => expect(previewWorkout).toHaveBeenCalledTimes(1));

    fireEvent.change(screen.getByLabelText("Дата"), {
      target: { value: nextDate },
    });

    await waitFor(() =>
      expect(previewWorkout).toHaveBeenCalledWith(
        expect.objectContaining({ date: nextDate }),
        null,
        expect.any(AbortSignal),
      ),
    );

    await act(async () => {
      second.resolve(
        caloriePreviewFixture({
          calculation: { active_kcal: 222, gross_kcal: 300, calories: 222 },
          explanation: calorieExplanationFixture({
            active_kcal: 222,
            gross_kcal: 300,
          }),
        }),
      );
    });

    await waitFor(() =>
      expect(screen.getByText(/≈ 222 ккал активности/)).toBeInTheDocument(),
    );

    await act(async () => {
      first.resolve(
        caloriePreviewFixture({
          calculation: { active_kcal: 111, gross_kcal: 200, calories: 111 },
          explanation: calorieExplanationFixture({
            active_kcal: 111,
            gross_kcal: 200,
          }),
        }),
      );
    });

    expect(screen.getByText(/≈ 222 ккал активности/)).toBeInTheDocument();
    expect(screen.queryByText(/≈ 111 ккал активности/)).not.toBeInTheDocument();
  });

  it("shows a preview network error instead of a successful zero", async () => {
    const user = userEvent.setup();
    vi.mocked(previewWorkout).mockRejectedValue(
      new Error("Сервер недоступен"),
    );

    renderWorkouts();
    await fillStrength(user);

    await waitFor(() => {
      expect(screen.getByRole("alert")).toHaveTextContent(
        "Не удалось получить расчёт",
      );
    });
    expect(
      screen.queryByText(/^≈? 0 ккал активности$/),
    ).not.toBeInTheDocument();
  });

  it("offers manual or an explicit incline-ignored estimate for run incline", async () => {
    const user = userEvent.setup();
    vi.mocked(previewWorkout).mockResolvedValue(
      caloriePreviewFixture({
        calculation: {
          calories_status: "unsupported",
          calories: null,
          active_kcal: null,
          gross_kcal: null,
          calorie_method: "unsupported_incline",
          met_code: null,
          met_value: null,
          met_label: null,
          calorie_approximate: false,
        },
        explanation: calorieExplanationFixture({
          status: "unsupported",
          active_kcal: null,
          gross_kcal: null,
          met: null,
          met_code: null,
          category: null,
          reason:
            "Расчёт с уклоном пока не поддерживается. Укажите калории вручную или подтвердите оценку без учёта уклона.",
          warnings: [],
          notes: [],
        }),
      }),
    );

    renderWorkouts();
    await user.click(
      screen.getByRole("button", { name: "Быстрый старт: Пробежка" }),
    );
    await replaceValue(user, "Длительность, мин", "30");
    await replaceValue(user, "Дистанция, км", "5");
    await replaceValue(user, "Уклон дорожки, %", "2.5");

    expect(await screen.findByText("Калории не рассчитаны")).toBeInTheDocument();
    const ignore = await screen.findByRole("button", {
      name: "Оценить без учёта уклона",
    });

    vi.mocked(previewWorkout).mockResolvedValue(
      caloriePreviewFixture({
        calculation: {
          active_kcal: 332,
          gross_kcal: 372,
          calories: 332,
          calorie_method: "run_speed",
          met_code: "12050",
          met_value: 9.3,
          met_label: "Бег 6,0–6,3 mph",
          incline_ignored: true,
        },
        explanation: calorieExplanationFixture({
          active_kcal: 332,
          gross_kcal: 372,
          met: 9.3,
          met_code: "12050",
          category: "Бег 6,0–6,3 mph",
          incline_ignored: true,
          warnings: ["Уклон не учтён: оценка дана для ровной поверхности."],
        }),
      }),
    );

    await user.click(ignore);
    await waitFor(() =>
      expect(screen.getByText(/≈ 332 ккал активности/)).toBeInTheDocument(),
    );

    await user.click(screen.getByRole("button", { name: "Сохранить тренировку" }));
    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0]).toMatchObject({
      calories_mode: "auto",
      incline_ignored: true,
    });
  });

  it("saves stationary cardio with power and without distance", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      screen.getByRole("button", { name: "Быстрый старт: Другое кардио" }),
    );

    await replaceValue(user, "Длительность, мин", "30");
    await user.selectOptions(screen.getByLabelText("Вид кардио"), "stationary_bike");
    await replaceValue(user, "Мощность, Вт", "100");

    expect(screen.getByLabelText(/Дистанция, км \(необязательно\)/)).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: "Сохранить тренировку" }));

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    const payload = vi.mocked(createWorkout).mock.calls[0][0];
    expect(payload).toMatchObject({
      type: "cardio",
      calories_mode: "auto",
      activity_kind: "stationary_bike",
      power_watts: 100,
    });
    expect(payload).not.toHaveProperty("distance_km");
  });

  it("offers outdoor intensity without speed and elliptical intensity", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    await user.click(
      screen.getByRole("button", { name: "Быстрый старт: Другое кардио" }),
    );

    await user.selectOptions(screen.getByLabelText("Вид кардио"), "outdoor_bike");
    expect(
      screen.getByLabelText("Нагрузка без скорости"),
    ).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText("Вид кардио"), "elliptical");
    expect(screen.getByLabelText("Нагрузка")).toBeInTheDocument();
    expect(
      screen.queryByLabelText("Нагрузка без скорости"),
    ).not.toBeInTheDocument();
  });

  it("shows ≈ for auto, no ≈ for manual, and honest text for null calories", () => {
    const auto = autoWorkout();
    const manualZero = workoutFixture({
      id: "manual-zero",
      name: "Ручной ноль",
      calories_mode: "manual",
      calories_status: "manual",
      calories: 0,
      calorie_explanation: calorieExplanationFixture({
        mode: "manual",
        status: "manual",
        active_kcal: null,
        gross_kcal: null,
        met: null,
        met_code: null,
        category: null,
        weight_kg: null,
        catalog_url: null,
        notes: [],
      }),
    });
    const missing = workoutFixture({
      id: "missing",
      name: "Без расчёта",
      calories_mode: "auto",
      calories_status: "missing",
      calories: null,
      calorie_explanation: calorieExplanationFixture({
        status: "missing",
        active_kcal: null,
        gross_kcal: null,
        met: null,
        met_code: null,
        category: null,
        weight_kg: null,
        catalog_url: null,
        reason: "Выберите характер силовой тренировки.",
        notes: [],
      }),
    });

    renderWorkouts(
      workoutDiaryFixture({ workouts: [auto, manualZero, missing] }),
    );

    const autoCard = screen.getByRole("region", { name: "Верх тела" });
    expect(
      within(autoCard).getAllByText(/≈ 150 ккал активности/).length,
    ).toBeGreaterThan(0);

    const manualCard = screen.getByRole("region", { name: "Ручной ноль" });
    expect(
      within(manualCard).getAllByText("0 ккал активности").length,
    ).toBeGreaterThan(0);
    expect(within(manualCard).queryByText(/≈ 0 ккал/)).not.toBeInTheDocument();

    const missingCard = screen.getByRole("region", { name: "Без расчёта" });
    expect(
      within(missingCard).getAllByText("Калории не рассчитаны").length,
    ).toBeGreaterThan(0);
    expect(screen.getByText(/Без расчёта калорий: 1/)).toBeInTheDocument();
  });

  it("recreates a deleted auto workout with its original weight and mode", async () => {
    const user = userEvent.setup();
    const original = autoWorkout();
    renderWorkouts(workoutDiaryFixture({ workouts: [original] }));

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

    await act(async () => {
      (action.onClick as () => void)();
    });

    await waitFor(() => expect(createWorkout).toHaveBeenCalledTimes(1));
    expect(vi.mocked(createWorkout).mock.calls[0][0]).toMatchObject({
      calories_mode: "auto",
      calorie_weight_kg: 80,
      calories_recalculate: true,
    });
  });

  it("opens an automatic workout for edit without forcing the saved weight as an override", async () => {
    const user = userEvent.setup();
    const original = autoWorkout();
    renderWorkouts(workoutDiaryFixture({ workouts: [original] }));

    const card = screen.getByRole("region", { name: "Верх тела" });
    await user.click(within(card).getByRole("button", { name: "Изменить" }));

    expect(
      screen.getByRole("dialog", { name: "Редактировать тренировку" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Вес для расчёта, кг")).toHaveValue("");

    await waitFor(() =>
      expect(previewWorkout).toHaveBeenCalledWith(
        expect.not.objectContaining({ calorie_weight_kg: expect.anything() }),
        original.id,
        expect.any(AbortSignal),
      ),
    );

    await user.click(screen.getByRole("button", { name: "Сохранить тренировку" }));
    await waitFor(() => expect(updateWorkout).toHaveBeenCalledTimes(1));

    expect(vi.mocked(updateWorkout).mock.calls[0][1]).toMatchObject({
      calories_mode: "auto",
      strength_character: "ordinary",
    });
    expect(vi.mocked(updateWorkout).mock.calls[0][1]).not.toHaveProperty(
      "calorie_weight_kg",
    );
  });

  it("forces a fresh preview only after the explicit recalculation button in edit mode", async () => {
    const user = userEvent.setup();
    const original = autoWorkout();
    renderWorkouts(workoutDiaryFixture({ workouts: [original] }));

    const card = screen.getByRole("region", { name: "Верх тела" });
    await user.click(within(card).getByRole("button", { name: "Изменить" }));

    const dialog = await screen.findByRole("dialog", {
      name: "Редактировать тренировку",
    });

    await waitFor(() => expect(previewWorkout).toHaveBeenCalled());
    expect(
      vi.mocked(previewWorkout).mock.calls.at(-1)?.[0],
    ).not.toHaveProperty("calories_recalculate");

    await user.click(
      within(dialog).getByRole("button", { name: "Рассчитать заново" }),
    );

    await waitFor(() => {
      expect(vi.mocked(previewWorkout).mock.calls.at(-1)?.[0]).toMatchObject({
        calories_recalculate: true,
      });
    });
  });

  it("previews a strength workout before name and exercises are filled, while save still validates them", async () => {
    const user = userEvent.setup();
    renderWorkouts();
    const dialog = await openStrength(user);

    await replaceValue(user, "Длительность, мин", "45");
    await user.selectOptions(
      screen.getByLabelText("Характер силовой тренировки"),
      "ordinary",
    );
    await user.clear(screen.getByLabelText("Название тренировки"));

    await waitFor(() => {
      expect(previewWorkout).toHaveBeenCalled();
      expect(screen.getByText(/≈ 150 ккал активности/)).toBeInTheDocument();
    });

    const payload = vi.mocked(previewWorkout).mock.calls.at(-1)?.[0];
    expect(payload).toMatchObject({
      type: "strength",
      duration_seconds: 2700,
      calories_mode: "auto",
      strength_character: "ordinary",
    });
    expect(payload).not.toHaveProperty("name");
    expect(payload).not.toHaveProperty("exercises");

    const panel = screen.getByLabelText("Предпросмотр расчёта калорий");
    expect(within(panel).getAllByText(/≈ 210 ккал всего/).length).toBeGreaterThan(0);
    expect(within(panel).getAllByText(/вес 80 кг/).length).toBeGreaterThan(0);

    await user.click(
      within(dialog).getByRole("button", { name: "Сохранить тренировку" }),
    );

    expect(
      await screen.findByText("Добавь название тренировки."),
    ).toBeInTheDocument();
    expect(
      screen.getByText("Укажи название упражнения."),
    ).toBeInTheDocument();
    expect(createWorkout).not.toHaveBeenCalled();
  });
});
