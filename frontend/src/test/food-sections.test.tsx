import { screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import { ApiError } from "@/lib/api";
import {
  changeFoodEntryMealType,
  createActivityEntry,
  createFoodEntry,
  fetchDashboard,
  fetchWeeklyStats,
  type ActivityEntry,
  type DashboardData,
  type FoodEntry,
  type FoodEntryInput,
  type MealType,
  type WeeklyData,
} from "@/lib/dashboard-api";
import { fetchCurrentGoal } from "@/lib/goal-api";
import { logoutUser } from "@/lib/auth-api";
import { useUiStore } from "@/stores/use-ui-store";
import { dashboardFixture, TEST_TODAY, weeklyFixture } from "@/test/fixtures";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/dashboard-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/dashboard-api")>();

  return {
    ...actual,
    fetchDashboard: vi.fn(),
    fetchWeeklyStats: vi.fn(),
    createFoodEntry: vi.fn(),
    deleteFoodEntry: vi.fn(),
    changeFoodEntryMealType: vi.fn(),
    createActivityEntry: vi.fn(),
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

const MEAL_TYPES = ["breakfast", "lunch", "dinner", "snack"] as const;

function entry(
  id: string,
  name: string,
  mealType: MealType | null,
  calories = 100,
  date = TEST_TODAY,
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

function foodTotals(entries: FoodEntry[]) {
  return entries.reduce(
    (totals, item) => ({
      calories: totals.calories + item.calories,
      protein: totals.protein + item.protein,
      fat: totals.fat + item.fat,
      carbs: totals.carbs + item.carbs,
    }),
    { calories: 0, protein: 0, fat: 0, carbs: 0 },
  );
}

function dashboardWith(
  entries: FoodEntry[],
  date = TEST_TODAY,
): DashboardData {
  const totals = foodTotals(entries);

  return dashboardFixture({
    date,
    target: {
      id: `goal-${date}`,
      effective_date: date,
      calories: 2310,
      protein: 128,
      fat: 64,
      carbs: 306,
      activity_level: "moderate",
      goal: "lose_weight",
    },
    food: {
      entries,
      totals,
      has_entries: entries.length > 0,
    },
    calories: {
      target: 2310,
      eaten: totals.calories,
      remaining: Math.max(0, 2310 - totals.calories),
      excess: Math.max(0, totals.calories - 2310),
      activity: 0,
    },
  });
}

let dashboards: Record<string, DashboardData>;
let weeks: Record<string, WeeklyData>;

function setDashboard(data: DashboardData): void {
  dashboards[data.date] = data;
}

function replaceFoodEntry(id: string, mealType: MealType): FoodEntry {
  const current = dashboards[TEST_TODAY];
  const entries = current.food.entries.map((item) =>
    item.id === id ? { ...item, meal_type: mealType } : item,
  );

  setDashboard(dashboardWith(entries));

  return entries.find((item) => item.id === id) as FoodEntry;
}

async function openGlobalFoodDialog() {
  const user = userEvent.setup();
  await user.click(screen.getByRole("button", { name: "＋ Добавить приём пищи" }));

  return {
    user,
    dialog: await screen.findByRole("dialog", { name: "Новая запись" }),
  };
}

async function fillFoodFields(
  user: ReturnType<typeof userEvent.setup>,
  name = "Борщ",
  calories = "250",
) {
  const nameInput = screen.getByLabelText("Название");
  await user.clear(nameInput);
  await user.type(nameInput, name);
  const caloriesInput = screen.getByLabelText("Калории, ккал");
  await user.clear(caloriesInput);
  await user.type(caloriesInput, calories);
  const protein = screen.getByLabelText("Белки, г");
  await user.clear(protein);
  await user.type(protein, "12");
  const fat = screen.getByLabelText("Жиры, г");
  await user.clear(fat);
  await user.type(fat, "6");
  const carbs = screen.getByLabelText("Углеводы, г");
  await user.clear(carbs);
  await user.type(carbs, "30");
}

beforeEach(() => {
  vi.clearAllMocks();
  dashboards = {};
  weeks = {};

  vi.mocked(fetchDashboard).mockImplementation(async (date) => {
    const key = date ?? TEST_TODAY;

    return dashboards[key] ?? dashboardWith([], key);
  });
  vi.mocked(fetchWeeklyStats).mockImplementation(async (start) => {
    const key = start ?? "2025-01-13";

    return weeks[key] ?? weeklyFixture({ start: key });
  });
  vi.mocked(fetchCurrentGoal).mockResolvedValue(null);
  vi.mocked(logoutUser).mockResolvedValue(undefined);
  vi.mocked(createFoodEntry).mockImplementation(async (input: FoodEntryInput) => {
    const created: FoodEntry = {
      id: "created-food",
      date: input.date,
      name: input.name,
      meal_type: input.meal_type,
      calories: input.calories,
      protein: input.protein,
      fat: input.fat,
      carbs: input.carbs,
    };

    setDashboard(dashboardWith([...dashboards[input.date].food.entries, created]));

    return created;
  });
  vi.mocked(changeFoodEntryMealType).mockImplementation(
    async (id: string, mealType: MealType) => replaceFoodEntry(id, mealType),
  );
  vi.mocked(createActivityEntry).mockImplementation(async (input) => {
    const created: ActivityEntry = { id: "activity-1", ...input };

    return created;
  });
});

describe("food diary meal sections", () => {
  it("always renders four ordered sections with totals, empty states, add buttons and the global counter", async () => {
    setDashboard(dashboardWith([]));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("Дневник питания")).toBeInTheDocument();
    await screen.findByRole("region", { name: "Завтрак" });
    expect(screen.getByText("0 записей")).toBeInTheDocument();

    const sections = MEAL_TYPES.map((mealType) => {
      const label = {
        breakfast: "Завтрак",
        lunch: "Обед",
        dinner: "Ужин",
        snack: "Перекусы",
      }[mealType];

      return screen.getByRole("region", { name: label });
    });

    expect(
      sections.map((section) => section.getAttribute("data-meal-type")),
    ).toEqual([...MEAL_TYPES]);

    for (const section of sections) {
      expect(
        within(section).getByText("Пока пусто — добавь запись."),
      ).toBeInTheDocument();
      expect(within(section).getByText(/^0/, { selector: "b" })).toBeInTheDocument();
    }

    expect(
      screen.getByRole("button", { name: "Добавить в «Завтрак»" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Добавить в «Обед»" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Добавить в «Ужин»" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Добавить в «Перекусы»" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "＋ Добавить приём пищи" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Без категории")).not.toBeInTheDocument();
  });

  it("prefills the section category and shows a created entry only in that section", async () => {
    const user = userEvent.setup();
    setDashboard(dashboardWith([]));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await screen.findByText("Дневник питания");
    await screen.findByRole("region", { name: "Обед" });
    await user.click(screen.getByRole("button", { name: "Добавить в «Обед»" }));

    expect(
      await screen.findByRole("dialog", { name: "Новая запись" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Приём пищи")).toHaveValue("lunch");

    await fillFoodFields(user, "Борщ", "250");
    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    await waitFor(() => {
      expect(createFoodEntry).toHaveBeenCalledWith({
        date: TEST_TODAY,
        name: "Борщ",
        meal_type: "lunch",
        calories: 250,
        protein: 12,
        fat: 6,
        carbs: 30,
      });
    });

    const lunch = await screen.findByRole("region", { name: "Обед" });
    expect(await within(lunch).findByText("Борщ")).toBeInTheDocument();
    expect(
      within(lunch).getByLabelText("Переместить «Борщ»"),
    ).toHaveValue("lunch");
    expect(screen.getByText("1 запись")).toBeInTheDocument();

    const breakfast = screen.getByRole("region", { name: "Завтрак" });
    expect(within(breakfast).queryByText("Борщ")).not.toBeInTheDocument();
    expect(
      within(breakfast).getByText("Пока пусто — добавь запись."),
    ).toBeInTheDocument();
  });

  it("requires a category in the global dialog and sends it after selection", async () => {
    setDashboard(dashboardWith([]));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    const { user } = await openGlobalFoodDialog();
    const mealSelect = screen.getByLabelText("Приём пищи");
    expect(mealSelect).toHaveValue("");

    await fillFoodFields(user, "Яблоко", "80");
    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    expect(await screen.findByText("Выберите приём пищи.")).toBeInTheDocument();
    expect(createFoodEntry).not.toHaveBeenCalled();
    expect(screen.getByRole("dialog", { name: "Новая запись" })).toBeInTheDocument();

    await user.selectOptions(mealSelect, "snack");
    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    await waitFor(() => {
      expect(createFoodEntry).toHaveBeenCalledWith(
        expect.objectContaining({ name: "Яблоко", meal_type: "snack" }),
      );
    });
    const snack = await screen.findByRole("region", { name: "Перекусы" });
    expect(await within(snack).findByText("Яблоко")).toBeInTheDocument();
  });

  it("keeps the selected category and every value when the server rejects the entry", async () => {
    setDashboard(dashboardWith([]));
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

    const { user } = await openGlobalFoodDialog();
    await user.selectOptions(screen.getByLabelText("Приём пищи"), "dinner");
    await fillFoodFields(user, "Ужин", "400");
    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    expect(await screen.findByText("Название отклонено.")).toBeInTheDocument();
    expect(screen.getByLabelText("Приём пищи")).toHaveValue("dinner");
    expect(screen.getByLabelText("Название")).toHaveValue("Ужин");
    expect(screen.getByLabelText("Калории, ккал")).toHaveValue("400");
    expect(screen.getByRole("dialog", { name: "Новая запись" })).toBeInTheDocument();
  });

  it("does not ask an activity entry for a meal category and never sends one", async () => {
    setDashboard(dashboardWith([]));

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    const user = userEvent.setup();
    await user.click(await screen.findByRole("button", { name: "＋ Добавить активность" }));
    await screen.findByRole("dialog", { name: "Новая запись" });

    expect(screen.queryByLabelText("Приём пищи")).not.toBeInTheDocument();

    const name = screen.getByLabelText("Название");
    await user.type(name, "Прогулка");
    const calories = screen.getByLabelText("Калории, ккал");
    await user.type(calories, "120");
    const steps = screen.getByLabelText("Шаги");
    await user.clear(steps);
    await user.type(steps, "4000");
    const minutes = screen.getByLabelText("Минуты");
    await user.clear(minutes);
    await user.type(minutes, "40");
    const distance = screen.getByLabelText("Расстояние, км");
    await user.clear(distance);
    await user.type(distance, "2,5");

    await user.click(screen.getByRole("button", { name: "Сохранить" }));

    await waitFor(() => {
      expect(createActivityEntry).toHaveBeenCalledTimes(1);
    });

    const payload = vi.mocked(createActivityEntry).mock.calls[0][0];
    expect(payload).not.toHaveProperty("meal_type");
    expect(payload).toEqual(
      expect.objectContaining({
        date: TEST_TODAY,
        name: "Прогулка",
        calories: 120,
        steps: 4000,
        minutes: 40,
        distance_km: 2.5,
      }),
    );
  });

  it("assigns a legacy row and moves entries between sections after the server confirms", async () => {
    setDashboard(
      dashboardWith([
        entry("legacy", "Яблоко", null, 80),
        entry("oats", "Овсянка", "breakfast", 300),
      ]),
    );

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    expect(await screen.findByText("Без категории")).toBeInTheDocument();
    const uncategorized = screen.getByRole("region", { name: "Без категории" });
    expect(within(uncategorized).getByText("Яблоко")).toBeInTheDocument();

    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");
    const user = userEvent.setup();

    await user.selectOptions(
      screen.getByLabelText("Переместить «Яблоко»"),
      "dinner",
    );

    await waitFor(() => {
      expect(changeFoodEntryMealType).toHaveBeenCalledWith("legacy", "dinner");
    });

    const dinner = await screen.findByRole("region", { name: "Ужин" });
    expect(await within(dinner).findByText("Яблоко")).toBeInTheDocument();
    expect(
      screen.queryByRole("region", { name: "Без категории" }),
    ).not.toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Завтрак" })).toHaveTextContent(
      "Овсянка",
    );
    expect(
      screen.getByLabelText("Переместить «Яблоко»"),
    ).toHaveValue("dinner");

    await waitFor(() => {
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["dashboard", TEST_TODAY],
      });
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["weekly", "2025-01-13"],
      });
    });

    expect(dashboards[TEST_TODAY].food.entries).toHaveLength(2);
    expect(
      dashboards[TEST_TODAY].food.entries.every(
        (item) => item.date === TEST_TODAY,
      ),
    ).toBe(true);
    expect(useUiStore.getState().selectedDate).toBeNull();

    await user.selectOptions(
      screen.getByLabelText("Переместить «Овсянка»"),
      "snack",
    );

    await waitFor(() => {
      expect(changeFoodEntryMealType).toHaveBeenCalledWith("oats", "snack");
    });

    const snack = await screen.findByRole("region", { name: "Перекусы" });
    expect(await within(snack).findByText("Овсянка")).toBeInTheDocument();
    const breakfast = screen.getByRole("region", { name: "Завтрак" });
    expect(within(breakfast).queryByText("Овсянка")).not.toBeInTheDocument();
    expect(useUiStore.getState().selectedDate).toBeNull();
  });

  it("reverts the selector to the server value when moving fails", async () => {
    setDashboard(dashboardWith([entry("oats", "Овсянка", "breakfast", 300)]));
    vi.mocked(changeFoodEntryMealType).mockRejectedValue(
      new ApiError(500, "Сервер недоступен"),
    );

    renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await screen.findByText("Овсянка");
    const user = userEvent.setup();
    const select = screen.getByLabelText("Переместить «Овсянка»");

    await user.selectOptions(select, "lunch");

    await waitFor(() => {
      expect(changeFoodEntryMealType).toHaveBeenCalledWith("oats", "lunch");
    });

    await waitFor(() => {
      expect(select).toHaveValue("breakfast");
      expect(select).not.toBeDisabled();
    });
    expect(screen.getByRole("region", { name: "Завтрак" })).toHaveTextContent(
      "Овсянка",
    );
  });

  it("does not change the selected day or leak data when a move resolves after a day switch", async () => {
    setDashboard(dashboardWith([entry("oats", "Овсянка", "breakfast", 300)]));
    setDashboard(dashboardWith([], "2025-01-22"));

    let resolveMove!: (entry: FoodEntry) => void;
    const pending = new Promise<FoodEntry>((resolve) => {
      resolveMove = resolve;
    });
    vi.mocked(changeFoodEntryMealType).mockReturnValue(pending);

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      seedDashboard: false,
    });

    await screen.findByText("Овсянка");
    const user = userEvent.setup();
    const select = screen.getByLabelText("Переместить «Овсянка»");

    await user.selectOptions(select, "lunch");

    await waitFor(() => {
      expect(changeFoodEntryMealType).toHaveBeenCalledWith("oats", "lunch");
    });
    expect(select).toBeDisabled();

    await user.click(screen.getByRole("button", { name: "Следующая неделя" }));

    await waitFor(() => {
      expect(useUiStore.getState().selectedDate).toBe("2025-01-22");
    });
    expect(
      await screen.findByRole("heading", { level: 2, name: /22 января 2025/ }),
    ).toBeInTheDocument();

    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");
    resolveMove(entry("oats", "Овсянка", "lunch", 300));

    await waitFor(() => {
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["dashboard", TEST_TODAY],
      });
      expect(invalidateSpy).toHaveBeenCalledWith({
        queryKey: ["weekly", "2025-01-13"],
      });
    });

    expect(useUiStore.getState().selectedDate).toBe("2025-01-22");
    expect(screen.queryByText("Овсянка")).not.toBeInTheDocument();
    expect(
      queryClient.getQueryState(["dashboard", "2025-01-22"])?.isInvalidated,
    ).toBe(false);
  });
});
