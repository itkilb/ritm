import type {
  DashboardData,
  FoodEntry,
  FoodMealGroup,
  FoodTotals,
  MealType,
  WeeklyData,
} from "@/lib/dashboard-api";
import { addIsoDays } from "@/lib/date";

export const TEST_TODAY = "2025-01-15";

const MEAL_ORDER: MealType[] = ["breakfast", "lunch", "dinner", "snack"];

function zeroTotals(): FoodTotals {
  return { calories: 0, protein: 0, fat: 0, carbs: 0 };
}

function round2(value: number): number {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

export function mealGroupsFor(entries: FoodEntry[]): {
  meal_groups: FoodMealGroup[];
  uncategorized: FoodMealGroup;
} {
  const groups: Record<MealType, FoodMealGroup> = {
    breakfast: { meal_type: "breakfast", entries: [], totals: zeroTotals(), count: 0 },
    lunch: { meal_type: "lunch", entries: [], totals: zeroTotals(), count: 0 },
    dinner: { meal_type: "dinner", entries: [], totals: zeroTotals(), count: 0 },
    snack: { meal_type: "snack", entries: [], totals: zeroTotals(), count: 0 },
  };
  const uncategorized: FoodMealGroup = {
    meal_type: null,
    entries: [],
    totals: zeroTotals(),
    count: 0,
  };

  for (const entry of entries) {
    const group = entry.meal_type ? groups[entry.meal_type] : uncategorized;
    group.entries.push(entry);
    group.count += 1;
    group.totals.calories += entry.calories;
    group.totals.protein += entry.protein;
    group.totals.fat += entry.fat;
    group.totals.carbs += entry.carbs;
  }

  for (const group of [...MEAL_ORDER.map((type) => groups[type]), uncategorized]) {
    for (const key of Object.keys(group.totals) as Array<keyof FoodTotals>) {
      group.totals[key] = round2(group.totals[key]);
    }
  }

  return {
    meal_groups: MEAL_ORDER.map((type) => groups[type]),
    uncategorized,
  };
}
export const TEST_WEEK_START = "2025-01-13";

export type DashboardOverrides = Omit<Partial<DashboardData>, "food"> & {
  food?: Partial<DashboardData["food"]>;
};

export function dashboardFixture(
  overrides: DashboardOverrides = {},
): DashboardData {
  const date = overrides.date ?? TEST_TODAY;
  const entries = overrides.food?.entries ?? [];
  const fallbackGroups = mealGroupsFor(entries);
  const food: DashboardData["food"] = {
    entries,
    totals: overrides.food?.totals ?? zeroTotals(),
    has_entries: overrides.food?.has_entries ?? false,
    meal_groups: overrides.food?.meal_groups ?? fallbackGroups.meal_groups,
    uncategorized:
      overrides.food?.uncategorized ?? fallbackGroups.uncategorized,
  };

  const result = {
    date,
    timezone: "Europe/Moscow",
    today: TEST_TODAY,
    target: null,
    food,
    activity: {
      entries: [],
      workouts: [],
      totals: { calories: 0, steps: 0, minutes: 0, distance_km: 0 },
      manual_totals: { calories: 0, steps: 0, minutes: 0, distance_km: 0 },
      workout_totals: { calories: 0, minutes: 0, distance_km: 0, count: 0 },
      has_entries: false,
    },
    water: { amount_ml: 0, target_ml: 2000 },
    calories: {
      target: null,
      eaten: 0,
      remaining: null,
      excess: null,
      activity: 0,
    },
    macros: {
      protein: { consumed: 0, target: null },
      fat: { consumed: 0, target: null },
      carbs: { consumed: 0, target: null },
    },
    steps: {
      total: 0,
      goal: 10000,
      remaining: 10000,
      minutes: 0,
      distance_km: 0,
    },
    ...overrides,
  } as DashboardData;

  result.food = food;

  return result;
}

export function weeklyFixture(
  overrides: Partial<WeeklyData> = {},
): WeeklyData {
  const start = overrides.start ?? TEST_WEEK_START;

  return {
    start,
    end: addIsoDays(start, 6),
    timezone: "Europe/Moscow",
    today: TEST_TODAY,
    days: Array.from({ length: 7 }).map((_, index) => ({
      date: addIsoDays(start, index),
      eaten: null,
      target: null,
      has_entries: false,
      progress: null,
      is_over_target: false,
      excess: null,
    })),
    eaten_total: 0,
    has_entries: false,
    ...overrides,
  };
}
