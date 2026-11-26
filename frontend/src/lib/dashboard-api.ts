import { apiRequest } from "@/lib/api";
import type { ActivityLevel, NutritionGoalType } from "@/lib/goal-api";
import type { Workout } from "@/lib/workout-api";

export const MEAL_TYPES = ["breakfast", "lunch", "dinner", "snack"] as const;

export type MealType = (typeof MEAL_TYPES)[number];

export const MEAL_TYPE_LABELS: Record<MealType, string> = {
  breakfast: "Завтрак",
  lunch: "Обед",
  dinner: "Ужин",
  snack: "Перекусы",
};

export type FoodEntry = {
  id: string;
  date: string;
  name: string;
  meal_type: MealType | null;
  calories: number;
  protein: number;
  fat: number;
  carbs: number;
};

export type ActivityEntry = {
  id: string;
  date: string;
  name: string;
  calories: number;
  steps: number;
  minutes: number;
  distance_km: number;
};

export type DashboardTarget = {
  id: string;
  effective_date: string;
  calories: number;
  protein: number;
  fat: number;
  carbs: number;
  activity_level: ActivityLevel;
  goal: NutritionGoalType;
};

export type MacroSummary = {
  consumed: number;
  target: number | null;
};

export type FoodTotals = {
  calories: number;
  protein: number;
  fat: number;
  carbs: number;
};

export type FoodMealGroup = {
  meal_type: MealType | null;
  entries: FoodEntry[];
  totals: FoodTotals;
  count: number;
};

export type ActivityTotals = {
  calories: number;
  steps: number;
  minutes: number;
  distance_km: number;
};

export type WorkoutActivityTotals = {
  calories: number;
  minutes: number;
  distance_km: number;
  count: number;
  uncalculated_count?: number;
};

export type DashboardData = {
  date: string;
  timezone: string;
  today: string;
  target: DashboardTarget | null;
  food: {
    entries: FoodEntry[];
    totals: FoodTotals;
    has_entries: boolean;
    meal_groups: FoodMealGroup[];
    uncategorized: FoodMealGroup;
  };
  activity: {
    entries: ActivityEntry[];
    workouts: Workout[];
    totals: ActivityTotals;
    manual_totals: ActivityTotals;
    workout_totals: WorkoutActivityTotals;
    has_entries: boolean;
  };
  water: {
    amount_ml: number;
    target_ml: number;
  };
  calories: {
    target: number | null;
    eaten: number;
    remaining: number | null;
    excess: number | null;
    activity: number;
  };
  macros: {
    protein: MacroSummary;
    fat: MacroSummary;
    carbs: MacroSummary;
  };
  steps: {
    total: number;
    goal: number;
    remaining: number;
    minutes: number;
    distance_km: number;
  };
};

export type WeeklyDay = {
  date: string;
  eaten: number | null;
  target: number | null;
  has_entries: boolean;
  progress: number | null;
  is_over_target: boolean;
  excess: number | null;
};

export type WeeklyData = {
  start: string;
  end: string;
  timezone: string;
  today: string;
  days: WeeklyDay[];
  eaten_total: number;
  has_entries: boolean;
};

export type WaterState = {
  date: string;
  amount_ml: number;
  target_ml: number;
};

export type FoodEntryInput = {
  date: string;
  name: string;
  meal_type: MealType;
  calories: number;
  protein: number;
  fat: number;
  carbs: number;
};

export type ActivityEntryInput = {
  date: string;
  name: string;
  calories: number;
  steps: number;
  minutes: number;
  distance_km: number;
};

type DataEnvelope<T> = { data: T };

function dateQuery(value: string | null | undefined): string {
  return value ? `?date=${encodeURIComponent(value)}` : "";
}

function startQuery(value: string | null | undefined): string {
  return value ? `?start=${encodeURIComponent(value)}` : "";
}

export async function fetchDashboard(
  date?: string | null,
): Promise<DashboardData> {
  const payload = await apiRequest<DataEnvelope<DashboardData>>(
    `/v1/dashboard${dateQuery(date)}`,
  );

  return payload.data;
}

export async function fetchWeeklyStats(
  start?: string | null,
): Promise<WeeklyData> {
  const payload = await apiRequest<DataEnvelope<WeeklyData>>(
    `/v1/weekly-stats${startQuery(start)}`,
  );

  return payload.data;
}

export async function createFoodEntry(
  input: FoodEntryInput,
): Promise<FoodEntry> {
  const payload = await apiRequest<DataEnvelope<FoodEntry>>("/v1/food-entries", {
    method: "POST",
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function changeFoodEntryMealType(
  id: string,
  mealType: MealType,
): Promise<FoodEntry> {
  const payload = await apiRequest<DataEnvelope<FoodEntry>>(
    `/v1/food-entries/${encodeURIComponent(id)}/meal-type`,
    {
      method: "PATCH",
      body: JSON.stringify({ meal_type: mealType }),
    },
  );

  return payload.data;
}

export async function deleteFoodEntry(id: string): Promise<void> {
  await apiRequest<void>(`/v1/food-entries/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}

export async function createActivityEntry(
  input: ActivityEntryInput,
): Promise<ActivityEntry> {
  const payload = await apiRequest<DataEnvelope<ActivityEntry>>(
    "/v1/activity-entries",
    {
      method: "POST",
      body: JSON.stringify(input),
    },
  );

  return payload.data;
}

export async function addWater(
  date: string,
  amountMl = 250,
): Promise<WaterState> {
  const payload = await apiRequest<DataEnvelope<WaterState>>(
    "/v1/water-entries",
    {
      method: "POST",
      body: JSON.stringify({ date, amount_ml: amountMl }),
    },
  );

  return payload.data;
}
