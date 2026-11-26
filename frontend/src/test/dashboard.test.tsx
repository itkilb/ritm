import { fireEvent, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import { ApiError } from "@/lib/api";
import {
  addWater,
  createActivityEntry,
  createFoodEntry,
  deleteFoodEntry,
  fetchDashboard,
  fetchWeeklyStats,
  type DashboardData,
  type FoodEntry,
  type WeeklyData,
  type WeeklyDay,
} from "@/lib/dashboard-api";
import { fetchCurrentGoal } from "@/lib/goal-api";
import { logoutUser } from "@/lib/auth-api";
import { startOfIsoWeek } from "@/lib/date";
import { useUiStore } from "@/stores/use-ui-store";
import {
  dashboardFixture,
  mealGroupsFor,
  TEST_TODAY,
  TEST_WEEK_START,
  weeklyFixture,
} from "@/test/fixtures";
import { progressFixture } from "@/test/progress-fixtures";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/dashboard-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/dashboard-api")>();

  return {
    ...actual,
    fetchDashboard: vi.fn(),
    fetchWeeklyStats: vi.fn(),
    createFoodEntry: vi.fn(),
    deleteFoodEntry: vi.fn(),
    createActivityEntry: vi.fn(),
    addWater: vi.fn(),
  };
});

vi.mock("@/lib/goal-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/goal-api")>();

  return {
    ...actual,
    fetchCurrentGoal: vi.fn(),
  };
});

vi.mock("@/lib/auth-api", () => ({
  registerUser: vi.fn(),
  loginUser: vi.fn(),
  logoutUser: vi.fn(),
}));

const profile = {
  weight_kg: 80,
  height_cm: 180,
  birth_date: "1990-01-15",
  fat_percentage: null,
  gender: "male" as const,
  activity_level: "moderate" as const,
  goal: "lose_weight" as const,
  timezone: "Europe/Moscow",
};

const completedUser: SessionUser = {
  id: 2,
  email: "completed@example.com",
  name: null,
  profile_completed: true,
  profile,
};

function goalDashboard(
  date: string,
  calories: number,
  macroTargets: { protein: number; fat: number; carbs: number },
  overrides: Parameters<typeof dashboardFixture>[0] = {},
): DashboardData {
  return dashboardFixture({
    date,
    target: {
      id: `goal-${date}`,
      effective_date: date,
      calories,
      protein: macroTargets.protein,
      fat: macroTargets.fat,
      carbs: macroTargets.carbs,
      activity_level: "moderate",
      goal: "lose_weight",
    },
    calories: {
      target: calories,
      eaten: 0,
      remaining: calories,
      excess: 0,
      activity: 0,
    },
    macros: {
      protein: { consumed: 0, target: macroTargets.protein },
      fat: { consumed: 0, target: macroTargets.fat },
      carbs: { consumed: 0, target: macroTargets.carbs },
    },
    ...overrides,
  });
}

function weekWith(
  start: string,
  days: Record<string, Partial<WeeklyDay>>,
): WeeklyData {
  const base = weeklyFixture({ start });
  const merged = base.days.map((day) => {
    const override = days[day.date];

    if (!override) {
      return { ...day, target: null };
    }

    return {
      ...day,
      ...override,
      has_entries: override.eaten !== null && override.eaten !== undefined,
    };
  });

  return {
    ...base,
    days: merged,
    eaten_total: merged.reduce((sum, day) => sum + (day.eaten ?? 0), 0),
    has_entries: merged.some((day) => day.has_entries),
  };
}

type TestUser = ReturnType<typeof userEvent.setup>;

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((resolvePromise) => {
    resolve = resolvePromise;
  });

  return { promise, resolve };
}

async function openEntryDialog(
  user: TestUser,
  opener = "＋ Добавить запись",
) {
  await user.click(screen.getByRole("button", { name: opener }));

  return screen.findByRole("dialog", { name: "Новая запись" });
}

async function replaceValue(user: TestUser, label: string, value: string) {
  const input = screen.getByLabelText(label);
  await user.clear(input);
  await user.type(input, value);
}

async function fillFoodDialog(
  user: TestUser,
  name: string,
  calories: string,
  opener = "＋ Добавить запись",
  mealType = "breakfast",
) {
  await openEntryDialog(user, opener);
  await user.selectOptions(screen.getByLabelText("Приём пищи"), mealType);
  await replaceValue(user, "Название", name);
  await replaceValue(user, "Калории, ккал", calories);
  await replaceValue(user, "Белки, г", "10");
  await replaceValue(user, "Жиры, г", "5");
  await replaceValue(user, "Углеводы, г", "40");
}

async function fillActivityDialog(
  user: TestUser,
  name: string,
  steps: string,
  opener = "＋ Добавить активность",
) {
  await openEntryDialog(user, opener);
  await replaceValue(user, "Название", name);
  await replaceValue(user, "Калории, ккал", "200");
  await replaceValue(user, "Шаги", steps);
  await replaceValue(user, "Минуты", "40");
  await replaceValue(user, "Расстояние, км", "3,5");
}

async function submitDialog(user: TestUser) {
  await user.click(screen.getByRole("button", { name: "Сохранить" }));
}

function foodEntry(
  id: string,
  date: string,
  name: string,
  calories: number,
  mealType: FoodEntry["meal_type"] = "breakfast",
): FoodEntry {
  return {
    id,
    date,
    name,
    meal_type: mealType,
    calories,
    protein: 10,
    fat: 5,
    carbs: 20,
  };
}

let dashboards: Record<string, DashboardData>;
let weeks: Record<string, WeeklyData>;

function setDashboard(data: DashboardData): void {
  dashboards[data.date] = data;
}

beforeEach(() => {
  vi.clearAllMocks();
  dashboards = {};
  weeks = {};

  vi.mocked(fetchDashboard).mockImplementation(async (date) => {
    const key = date ?? TEST_TODAY;

    return dashboards[key] ?? dashboardFixture({ date: key });
  });
  vi.mocked(fetchWeeklyStats).mockImplementation(async (start) => {
    const key = start ?? TEST_WEEK_START;

    return weeks[key] ?? weeklyFixture({ start: key });
  });
  vi.mocked(fetchCurrentGoal).mockResolvedValue(null);
  vi.mocked(logoutUser).mockResolvedValue(undefined);
});

describe("dashboard", () => {
  it("adds food, updates balance, macros and the week ring through the dialog", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
      [TEST_TODAY]: { eaten: null, target: 2310, progress: null },
    });

    vi.mocked(createFoodEntry).mockImplementation(async (input) => {
      const entry = {
        id: "food-1",
        date: input.date,
        name: input.name,
        meal_type: input.meal_type,
        calories: input.calories,
        protein: input.protein,
        fat: input.fat,
        carbs: input.carbs,
      };

      setDashboard(
        goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }, {
          food: {
            entries: [entry],
            totals: { calories: 300, protein: 10, fat: 5, carbs: 40 },
            has_entries: true,
          },
          calories: {
            target: 2310,
            eaten: 300,
            remaining: 2010,
            excess: 0,
            activity: 0,
          },
          macros: {
            protein: { consumed: 10, target: 128 },
            fat: { consumed: 5, target: 64 },
            carbs: { consumed: 40, target: 306 },
          },
        }),
      );
      weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
        [TEST_TODAY]: {
          eaten: 300,
          target: 2310,
          progress: 300 / 2310,
          is_over_target: false,
          excess: 0,
        },
      });

      return entry;
    });

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("Дневник питания")).toBeInTheDocument();
    expect((await screen.findAllByText(/2\s*310/)).length).toBeGreaterThan(0);
    expect(queryClient.getQueryData(["dashboard", "today"])).toBeTruthy();

    await fillFoodDialog(user, "Овсянка", "300");
    await submitDialog(user);

    await waitFor(() => {
      expect(createFoodEntry).toHaveBeenCalledWith({
        date: TEST_TODAY,
        name: "Овсянка",
        meal_type: "breakfast",
        calories: 300,
        protein: 10,
        fat: 5,
        carbs: 40,
      });
    });

    expect(await screen.findByText("Овсянка")).toBeInTheDocument();
    await waitFor(() => {
      const balance = screen.getByRole("region", { name: "Баланс калорий" });
      expect(within(balance).getByText(/2\s*010/)).toBeInTheDocument();
    });
    expect(
      screen.getByRole("progressbar", { name: /Белки: 10 из 128/ }),
    ).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByLabelText(/съедено 300 ккал/)).toBeInTheDocument();
    });
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    expect(fetchDashboard).toHaveBeenCalledWith(null);
    expect(fetchWeeklyStats).toHaveBeenCalledWith(null);
  });

  it("keeps activity calories separate and never raises the food target", async () => {
    const user = userEvent.setup();
    setDashboard(
      goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }, {
        calories: {
          target: 2310,
          eaten: 1000,
          remaining: 1310,
          excess: 0,
          activity: 0,
        },
      }),
    );
    weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
      [TEST_TODAY]: { eaten: 1000, target: 2310, progress: 1000 / 2310 },
    });

    vi.mocked(createActivityEntry).mockImplementation(async (input) => {
      const entry = {
        id: "activity-1",
        date: input.date,
        name: input.name,
        calories: input.calories,
        steps: input.steps,
        minutes: input.minutes,
        distance_km: input.distance_km,
      };

      setDashboard(
        goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }, {
          activity: {
            entries: [entry],
            workouts: [],
            totals: {
              calories: 200,
              steps: 6000,
              minutes: 40,
              distance_km: 3.5,
            },
            manual_totals: {
              calories: 200,
              steps: 6000,
              minutes: 40,
              distance_km: 3.5,
            },
            workout_totals: {
              calories: 0,
              minutes: 0,
              distance_km: 0,
              count: 0,
            },
            has_entries: true,
          },
          calories: {
            target: 2310,
            eaten: 1000,
            remaining: 1310,
            excess: 0,
            activity: 200,
          },
          steps: {
            total: 6000,
            goal: 10000,
            remaining: 4000,
            minutes: 40,
            distance_km: 3.5,
          },
        }),
      );

      return entry;
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText(/1\s*310/)).toBeInTheDocument();
    const balance = screen.getByRole("region", { name: "Баланс калорий" });
    expect(within(balance).getByText(/1\s*310/)).toBeInTheDocument();

    await fillActivityDialog(user, "Прогулка", "6000");
    await submitDialog(user);

    await waitFor(() => {
      expect(createActivityEntry).toHaveBeenCalledWith({
        date: TEST_TODAY,
        name: "Прогулка",
        calories: 200,
        steps: 6000,
        minutes: 40,
        distance_km: 3.5,
      });
    });

    await waitFor(() => {
      const updatedBalance = screen.getByRole("region", {
        name: "Баланс калорий",
      });
      expect(within(updatedBalance).getByText(/1\s*310/)).toBeInTheDocument();
      expect(within(updatedBalance).getByText("200")).toBeInTheDocument();
    });

    const activity = screen.getByRole("region", { name: "Активность" });
    expect(within(activity).getByText(/6\s*000/)).toBeInTheDocument();
    expect(within(activity).getByText(/осталось 4\s*000/)).toBeInTheDocument();
    expect(screen.queryByText(/1\s*510/)).not.toBeInTheDocument();
    expect(screen.queryByText(/2\s*510/)).not.toBeInTheDocument();
  });

  it("adds nine glasses to 2250 ml and keeps all eight glasses full", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    vi.mocked(addWater).mockImplementation(async (date, amountMl = 250) => {
      const current = dashboards[date] ?? dashboardFixture({ date });
      const next = current.water.amount_ml + amountMl;

      setDashboard({
        ...current,
        water: { ...current.water, amount_ml: next },
      });

      return { date, amount_ml: next, target_ml: 2000 };
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("Водный баланс")).toBeInTheDocument();

    for (let glass = 1; glass <= 9; glass += 1) {
      const button = screen.getByRole("button", { name: "＋ Стакан" });
      await waitFor(() => expect(button).toBeEnabled());
      await user.click(button);

      await waitFor(() => {
        expect(addWater).toHaveBeenCalledTimes(glass);
      });
    }

    expect(addWater).toHaveBeenLastCalledWith(TEST_TODAY, 250);
    expect(await screen.findByText("2,25")).toBeInTheDocument();
    expect(screen.getByText("/ 2 л")).toBeInTheDocument();
    expect(screen.getAllByRole("img", { name: /заполнен/ })).toHaveLength(8);
    expect(screen.queryAllByRole("img", { name: /пуст/ })).toHaveLength(0);
  });

  it("switches day and week and returns to the fresh server today", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    setDashboard(
      goalDashboard("2025-01-22", 2000, { protein: 120, fat: 60, carbs: 250 }, {
        calories: {
          target: 2000,
          eaten: 500,
          remaining: 1500,
          excess: 0,
          activity: 0,
        },
      }),
    );
    setDashboard(goalDashboard("2025-01-20", 2100, { protein: 125, fat: 62, carbs: 260 }));
    weeks["2025-01-20"] = weekWith("2025-01-20", {
      "2025-01-20": { eaten: null, target: 2100, progress: null },
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("13–19 января 2025")).toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { level: 2, name: "Обзор дня" }),
    ).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Следующая неделя" }));
    await waitFor(() => {
      expect(fetchDashboard).toHaveBeenCalledWith("2025-01-22");
    });
    expect(await screen.findByText("20–26 января 2025")).toBeInTheDocument();
    expect(
      await screen.findByRole("heading", { level: 2, name: /22 января 2025/ }),
    ).toBeInTheDocument();
    const balance = screen.getByRole("region", { name: "Баланс калорий" });
    expect(within(balance).getByText(/1\s*500/)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: /^пн 20:/ }));
    await waitFor(() => {
      expect(fetchDashboard).toHaveBeenCalledWith("2025-01-20");
    });
    expect(
      await screen.findByRole("heading", { level: 2, name: /20 января 2025/ }),
    ).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Сегодня" }));
    await waitFor(() => {
      expect(useUiStore.getState().selectedDate).toBe(TEST_TODAY);
    });
    expect(
      await screen.findByRole("heading", { level: 2, name: "Обзор дня" }),
    ).toBeInTheDocument();
    expect(await screen.findByText("13–19 января 2025")).toBeInTheDocument();
  });

  it("keeps a pending entry on its captured date after switching days", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    setDashboard(
      goalDashboard("2025-01-22", 2000, { protein: 120, fat: 60, carbs: 250 }, {
        calories: {
          target: 2000,
          eaten: 500,
          remaining: 1500,
          excess: 0,
          activity: 0,
        },
      }),
    );

    const pending = deferred<FoodEntry>();
    vi.mocked(createFoodEntry).mockReturnValue(pending.promise);

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("Дневник питания")).toBeInTheDocument();

    await fillFoodDialog(user, "Ожидание", "100", "＋ Добавить приём пищи");
    await submitDialog(user);

    await waitFor(() => {
      expect(createFoodEntry).toHaveBeenCalledWith(
        expect.objectContaining({ date: TEST_TODAY, name: "Ожидание" }),
      );
    });

    await user.click(screen.getByRole("button", { name: "Следующая неделя" }));
    await waitFor(() => {
      expect(fetchDashboard).toHaveBeenCalledWith("2025-01-22");
    });
    expect(await screen.findByText("20–26 января 2025")).toBeInTheDocument();
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");
    pending.resolve(foodEntry("food-late", TEST_TODAY, "Ожидание", 100));

    await waitFor(() => {
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["dashboard", TEST_TODAY],
      });
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["weekly", TEST_WEEK_START],
      });
    });

    expect(useUiStore.getState().selectedDate).toBe("2025-01-22");
    expect(screen.queryByText("Ожидание")).not.toBeInTheDocument();
    expect(
      queryClient.getQueryState(["dashboard", "2025-01-22"])?.isInvalidated,
    ).toBe(false);
  });

  it("opens the unified entry dialog on the requested type and switches fields", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await openEntryDialog(user, "＋ Добавить активность");
    expect(screen.getByLabelText("Тип записи")).toHaveValue("activity");
    expect(screen.getByLabelText("Шаги")).toBeInTheDocument();
    expect(screen.queryByLabelText("Белки, г")).not.toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText("Тип записи"), "food");
    expect(screen.getByLabelText("Белки, г")).toBeInTheDocument();
    expect(screen.queryByLabelText("Шаги")).not.toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText("Тип записи"), "activity");
    expect(screen.getByLabelText("Расстояние, км")).toBeInTheDocument();
  });

  it("keeps the dialog and every entered value when the server rejects an entry", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    vi.mocked(createFoodEntry).mockRejectedValue(
      new ApiError(422, "Ошибка валидации", {
        message: "Ошибка валидации",
        errors: { name: ["Название отклонено."] },
      }),
    );

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await fillFoodDialog(user, "Мой завтрак", "300", "＋ Добавить приём пищи");
    await submitDialog(user);

    expect(await screen.findByText("Название отклонено.")).toBeInTheDocument();
    expect(screen.getByRole("dialog", { name: "Новая запись" })).toBeInTheDocument();
    expect(screen.getByLabelText("Название")).toHaveValue("Мой завтрак");
    expect(screen.getByLabelText("Калории, ккал")).toHaveValue("300");
    expect(
      screen.getByRole("button", { name: "Сохранить" }),
    ).toBeEnabled();
  });

  it("guards against a duplicate submit and keeps the dialog open while pending", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    const pending = deferred<FoodEntry>();
    vi.mocked(createFoodEntry).mockReturnValue(pending.promise);

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await fillFoodDialog(user, "Двойная", "100", "＋ Добавить приём пищи");
    const submit = screen.getByRole("button", { name: "Сохранить" });
    await user.click(submit);
    await user.click(submit);

    expect(createFoodEntry).toHaveBeenCalledTimes(1);
    expect(
      screen.getByRole("button", { name: "Сохраняем…" }),
    ).toBeDisabled();
    expect(screen.getByRole("dialog", { name: "Новая запись" })).toBeInTheDocument();

    pending.resolve(foodEntry("food-once", TEST_TODAY, "Двойная", 100));
    await waitFor(() => {
      expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
    });
  });

  it("deletes a food entry and refreshes the day and week", async () => {
    const user = userEvent.setup();
    setDashboard(
      goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }, {
        food: {
          entries: [
            {
              id: "food-1",
              date: TEST_TODAY,
              name: "Овсянка",
              meal_type: "breakfast",
              calories: 300,
              protein: 10,
              fat: 5,
              carbs: 40,
            },
            {
              id: "food-2",
              date: TEST_TODAY,
              name: "Суп",
              meal_type: "lunch",
              calories: 200,
              protein: 8,
              fat: 6,
              carbs: 20,
            },
          ],
          totals: { calories: 500, protein: 18, fat: 11, carbs: 60 },
          has_entries: true,
        },
        calories: {
          target: 2310,
          eaten: 500,
          remaining: 1810,
          excess: 0,
          activity: 0,
        },
      }),
    );
    weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
      [TEST_TODAY]: { eaten: 500, target: 2310, progress: 500 / 2310 },
    });

    vi.mocked(deleteFoodEntry).mockImplementation(async (id) => {
      const current = dashboards[TEST_TODAY];
      const remaining = current.food.entries.filter((entry) => entry.id !== id);

      setDashboard({
        ...current,
        food: {
          entries: remaining,
          totals: { calories: 300, protein: 10, fat: 5, carbs: 40 },
          has_entries: true,
          ...mealGroupsFor(remaining),
        },
        calories: {
          target: 2310,
          eaten: 300,
          remaining: 2010,
          excess: 0,
          activity: 0,
        },
      });
      weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
        [TEST_TODAY]: { eaten: 300, target: 2310, progress: 300 / 2310 },
      });
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("Овсянка")).toBeInTheDocument();
    await user.click(screen.getByRole("button", { name: /Удалить «Овсянка»/ }));

    await waitFor(() => {
      expect(deleteFoodEntry).toHaveBeenCalledWith("food-1");
    });
    await waitFor(() => {
      expect(screen.queryByText("Овсянка")).not.toBeInTheDocument();
    });
    const balance = screen.getByRole("region", { name: "Баланс калорий" });
    expect(within(balance).getByText(/2\s*010/)).toBeInTheDocument();
    expect(screen.getByLabelText(/съедено 300 ккал/)).toBeInTheDocument();
  });

  it("clears the selected day and query cache on logout", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["private-user-data"], "secret"]],
      selectedDate: TEST_TODAY,
      seedDashboard: false,
    });

    expect(await screen.findByText("Дневник питания")).toBeInTheDocument();
    expect(useUiStore.getState().selectedDate).toBe(TEST_TODAY);

    await user.click(screen.getByRole("button", { name: "Выйти" }));

    await waitFor(() => {
      expect(useUiStore.getState().selectedDate).toBeNull();
    });
    expect(queryClient.getQueryData(["session"])).toBeNull();
    expect(queryClient.getQueryData(["private-user-data"])).toBeUndefined();
    expect(
      await screen.findByRole("button", { name: "Войти" }),
    ).toBeInTheDocument();
  });

  it("refreshes the server today in one click when the default query key stays the same", async () => {
    const user = userEvent.setup();
    const todayA = "2025-01-19";
    const todayB = "2025-01-20";
    let serverToday = todayA;

    setDashboard(goalDashboard(todayA, 2310, { protein: 128, fat: 64, carbs: 306 }));
    setDashboard(goalDashboard(todayB, 2200, { protein: 120, fat: 60, carbs: 250 }));
    weeks[startOfIsoWeek(todayA)] = weekWith(startOfIsoWeek(todayA), {
      [todayA]: { eaten: 100, target: 2310, progress: 100 / 2310 },
    });
    weeks[startOfIsoWeek(todayB)] = weekWith(startOfIsoWeek(todayB), {
      [todayB]: { eaten: 200, target: 2200, progress: 200 / 2200 },
    });

    vi.mocked(fetchDashboard).mockImplementation(async (date) => {
      const key = date ?? serverToday;
      const data = dashboards[key] ?? dashboardFixture({ date: key });

      return {
        ...data,
        date: key,
        today: serverToday,
        timezone: "Pacific/Kiritimati",
      };
    });
    vi.mocked(fetchWeeklyStats).mockImplementation(async (start) => {
      const key = start ?? startOfIsoWeek(serverToday);

      return (
        weeks[key] ??
        weeklyFixture({
          start: key,
          today: serverToday,
          timezone: "Pacific/Kiritimati",
        })
      );
    });

    const timezoneUser: SessionUser = {
      ...completedUser,
      profile: { ...profile, timezone: "Pacific/Kiritimati" },
    };
    const { queryClient } = renderApp({
      user: timezoneUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(
      await screen.findByRole("heading", { level: 2, name: "Обзор дня" }),
    ).toBeInTheDocument();
    expect(await screen.findByText("13–19 января 2025")).toBeInTheDocument();

    const dashboardTodayCallsBefore = vi
      .mocked(fetchDashboard)
      .mock.calls.filter(([date]) => date === null || date === undefined).length;
    const weeklyTodayCallsBefore = vi
      .mocked(fetchWeeklyStats)
      .mock.calls.filter(([start]) => start === null || start === undefined).length;

    serverToday = todayB;

    await user.click(screen.getByRole("button", { name: "Сегодня" }));

    await waitFor(() => {
      expect(useUiStore.getState().selectedDate).toBe(todayB);
    });
    expect(
      await screen.findByRole("heading", { level: 2, name: "Обзор дня" }),
    ).toBeInTheDocument();
    expect(await screen.findByText("20–26 января 2025")).toBeInTheDocument();
    await waitFor(() => {
      expect(
        vi
          .mocked(fetchDashboard)
          .mock.calls.filter(([date]) => date === null || date === undefined)
          .length,
      ).toBeGreaterThan(dashboardTodayCallsBefore);
      expect(
        vi
          .mocked(fetchWeeklyStats)
          .mock.calls.filter(([start]) => start === null || start === undefined)
          .length,
      ).toBeGreaterThan(weeklyTodayCallsBefore);
    });
    expect(
      queryClient.getQueryData<DashboardData>(["dashboard", "today"])?.today,
    ).toBe(todayB);
    await waitFor(() => {
      expect(fetchWeeklyStats).toHaveBeenCalledWith(startOfIsoWeek(todayB));
    });
  });

  it("refreshes the server today with one click when a stale date is selected", async () => {
    const user = userEvent.setup();
    const todayA = "2025-01-19";
    const todayB = "2025-01-20";
    let serverToday = todayA;

    setDashboard(goalDashboard(todayA, 2310, { protein: 128, fat: 64, carbs: 306 }));
    setDashboard(goalDashboard(todayB, 2200, { protein: 120, fat: 60, carbs: 250 }));
    weeks[startOfIsoWeek(todayA)] = weekWith(startOfIsoWeek(todayA), {
      [todayA]: { eaten: 100, target: 2310, progress: 100 / 2310 },
    });
    weeks[startOfIsoWeek(todayB)] = weekWith(startOfIsoWeek(todayB), {
      [todayB]: { eaten: 200, target: 2200, progress: 200 / 2200 },
    });

    vi.mocked(fetchDashboard).mockImplementation(async (date) => {
      const key = date ?? serverToday;
      const data = dashboards[key] ?? dashboardFixture({ date: key });

      return { ...data, date: key, today: serverToday };
    });
    vi.mocked(fetchWeeklyStats).mockImplementation(async (start) => {
      const key = start ?? startOfIsoWeek(serverToday);

      return weeks[key] ?? weeklyFixture({ start: key, today: serverToday });
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      selectedDate: todayA,
      seedDashboard: false,
    });

    expect(
      await screen.findByRole("heading", { level: 2, name: /19 января 2025/ }),
    ).toBeInTheDocument();

    serverToday = todayB;

    await user.click(screen.getByRole("button", { name: "Сегодня" }));

    await waitFor(() => {
      expect(useUiStore.getState().selectedDate).toBe(todayB);
    });
    expect(
      await screen.findByRole("heading", { level: 2, name: "Обзор дня" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Выбрать день")).toHaveValue(todayB);
    expect(await screen.findByText("20–26 января 2025")).toBeInTheDocument();
    await waitFor(() => {
      expect(fetchDashboard).toHaveBeenCalledWith(todayB);
      expect(fetchWeeklyStats).toHaveBeenCalledWith(startOfIsoWeek(todayB));
    });
  });

  it("shows the day overview heading only for the current server date", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    setDashboard(
      goalDashboard("2025-01-22", 2000, { protein: 120, fat: 60, carbs: 250 }),
    );

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(
      await screen.findByRole("heading", { level: 2, name: "Обзор дня" }),
    ).toBeInTheDocument();
    expect(await screen.findByText(/Всё важное — в одном месте/)).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Следующая неделя" }));

    expect(
      await screen.findByRole("heading", { level: 2, name: /22 января 2025/ }),
    ).toBeInTheDocument();
    expect(
      screen.queryByRole("heading", { level: 2, name: "Обзор дня" }),
    ).not.toBeInTheDocument();
    expect(await screen.findByText(/Всё важное — в одном месте/)).toBeInTheDocument();
  });

  it("renders seven ordered week days, clamps over-target progress and keeps empty days as dashes", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
      [TEST_TODAY]: {
        eaten: 2500,
        target: 2310,
        progress: 1,
        is_over_target: true,
        excess: 190,
      },
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("13–19 января 2025")).toBeInTheDocument();
    const dayButtons = screen.getAllByRole("button", {
      name: /^(пн|вт|ср|чт|пт|сб|вс) \d{1,2}:/,
    });
    expect(dayButtons).toHaveLength(7);
    expect(dayButtons.map((button) => button.textContent?.slice(0, 2))).toEqual([
      "пн",
      "вт",
      "ср",
      "чт",
      "пт",
      "сб",
      "вс",
    ]);

    const selectedDay = screen.getByRole("button", { name: /^ср 15:/ });
    expect(selectedDay).toHaveClass("selected");
    expect(selectedDay).toHaveClass("is-over");
    const ring = selectedDay.querySelector(".week-ring") as HTMLElement;
    expect(ring.style.getPropertyValue("--progress")).toBe("100%");

    const emptyDay = screen.getByRole("button", { name: /^пн 13:/ });
    expect(within(emptyDay).getByText("—")).toBeInTheDocument();
    expect(within(emptyDay).getByText("/ —")).toBeInTheDocument();

    await user.click(emptyDay);
    expect(useUiStore.getState().selectedDate).toBe("2025-01-13");
  });

  it("wires sidebar navigation to sections and navigates to the protected progress screen", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    weeks[TEST_WEEK_START] = weekWith(TEST_WEEK_START, {
      "2025-01-13": { eaten: 700, target: 2200, progress: 700 / 2200 },
      [TEST_TODAY]: {
        eaten: 500,
        target: 2310,
        progress: 500 / 2310,
      },
    });

    renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [
        [
          ["goals", "current"],
          { calories: 2440, effective_date: TEST_TODAY },
        ],
        [["progress", "month", "today", null], progressFixture()],
      ],
      seedDashboard: false,
    });

    expect(await screen.findByText("Дневник питания")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Питание" }));
    expect(Element.prototype.scrollIntoView).toHaveBeenCalledWith({
      behavior: "smooth",
      block: "start",
    });
    expect(
      screen.getByRole("button", { name: "Питание" }),
    ).toHaveAttribute("aria-current", "page");

    await user.click(screen.getByRole("button", { name: "Активность" }));
    expect(Element.prototype.scrollIntoView).toHaveBeenCalledTimes(2);

    await user.click(screen.getByRole("button", { name: "Мой день" }));
    expect(window.scrollTo).toHaveBeenCalledWith({
      top: 0,
      behavior: "smooth",
    });

    const progressButton = screen.getByRole("button", { name: "Прогресс" });
    expect(progressButton).not.toHaveAttribute("aria-haspopup");
    await user.click(progressButton);

    expect(
      await screen.findByRole("heading", { name: "Прогресс" }),
    ).toBeInTheDocument();
    expect(screen.getByRole("tab", { name: "Вес" })).toHaveAttribute(
      "aria-selected",
      "true",
    );
    expect(screen.getByText("Текущий вес")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Прогресс" }),
    ).toHaveAttribute("aria-current", "page");
    expect(screen.getByRole("button", { name: "Прогресс — мобильная навигация" })).toHaveAttribute("aria-current", "page");
  });

  it("traps dialog focus, locks scroll, closes on Escape/backdrop and returns focus", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    const opener = await screen.findByRole("button", {
      name: "＋ Добавить запись",
    });
    await user.click(opener);

    const dialog = await screen.findByRole("dialog", { name: "Новая запись" });
    expect(screen.getByLabelText("Название")).toHaveFocus();
    expect(document.body.style.overflow).toBe("hidden");
    expect(dialog).toHaveAttribute("aria-modal", "true");

    const typeSelect = screen.getByLabelText("Тип записи");
    const cancel = screen.getByRole("button", { name: "Отмена" });
    cancel.focus();
    fireEvent.keyDown(cancel, { key: "Tab" });
    expect(typeSelect).toHaveFocus();
    typeSelect.focus();
    fireEvent.keyDown(typeSelect, { key: "Tab", shiftKey: true });
    expect(cancel).toHaveFocus();

    await user.keyboard("{Escape}");

    await waitFor(() => {
      expect(
        screen.queryByRole("dialog", { name: "Новая запись" }),
      ).not.toBeInTheDocument();
    });
    expect(opener).toHaveFocus();
    expect(document.body.style.overflow).not.toBe("hidden");

    await user.click(opener);
    await screen.findByRole("dialog", { name: "Новая запись" });
    await user.click(document.querySelector(".modal-backdrop") as HTMLElement);

    await waitFor(() => {
      expect(
        screen.queryByRole("dialog", { name: "Новая запись" }),
      ).not.toBeInTheDocument();
    });
  });

  it("opens the prefilled questionnaire from the profile block and returns without saving", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await user.click(
      await screen.findByRole("button", {
        name: "Открыть параметры и цель",
      }),
    );

    expect(
      await screen.findByRole("heading", { name: "Настроим твой баланс" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Вес, кг")).toHaveValue(80);
    expect(screen.getByLabelText("Рост, см")).toHaveValue(180);
    expect(screen.getByLabelText("Дата рождения")).toHaveValue("1990-01-15");
    expect(screen.getByLabelText("Пол для расчёта")).toHaveValue("male");
    expect(screen.getByLabelText("Уровень активности")).toHaveValue("moderate");
    expect(screen.getByRole("radio", { name: /Похудение/ })).toBeChecked();

    await user.click(screen.getByRole("button", { name: "Отмена" }));
    expect(
      await screen.findByRole("heading", { name: /Хороший день для себя/ }),
    ).toBeInTheDocument();
  });

  it("offers a logout retry and does not claim the session was closed on failure", async () => {
    const user = userEvent.setup();
    setDashboard(goalDashboard(TEST_TODAY, 2310, { protein: 128, fat: 64, carbs: 306 }));
    vi.mocked(logoutUser).mockRejectedValueOnce(
      new ApiError(500, "Запрос завершился с ошибкой 500"),
    );

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["private-user-data"], "secret"]],
      seedDashboard: false,
    });

    await user.click(
      await screen.findByRole("button", { name: "Выйти" }),
    );

    expect(
      await screen.findByText(/Не удалось выйти из аккаунта/),
    ).toBeInTheDocument();
    expect(screen.getByText(/Запрос завершился с ошибкой 500/)).toBeInTheDocument();
    expect(queryClient.getQueryData(["session"])).toEqual(completedUser);
    expect(screen.getByRole("button", { name: "Повторить выход" })).toBeInTheDocument();
  });
});
