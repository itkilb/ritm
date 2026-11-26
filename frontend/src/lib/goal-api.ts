import { apiRequest } from "@/lib/api";

export type Gender = "male" | "female";
export type ActivityLevel = "sedentary" | "light" | "moderate" | "high";
export type NutritionGoalType = "lose_weight" | "gain_muscle";

export type ProfileInput = {
  weight_kg: number;
  height_cm: number;
  birth_date: string;
  fat_percentage: number | null;
  gender: Gender;
  activity_level: ActivityLevel;
  goal: NutritionGoalType;
  timezone: string;
};

export type NutritionExplanation = {
  age: number;
  bmr: number;
  tdee: number;
  activity_coefficient: number;
  activity_label: string;
  goal_multiplier: number;
  goal_label: string;
  lean_mass_kg: number | null;
  protein_basis: "body_weight" | "lean_mass";
  principles: string[];
};

export type NutritionPreview = ProfileInput & {
  calories: number;
  protein: number;
  fat: number;
  carbs: number;
  explanation: NutritionExplanation;
};

export type NutritionGoal = NutritionPreview & {
  id: string;
  effective_date: string;
};

export type SavedUser = {
  id: number | string;
  name?: string | null;
  email?: string | null;
  profile_completed: boolean;
  profile?: ProfileInput | null;
};

type DataEnvelope<T> = {
  data: T;
};

export async function previewNutritionGoal(
  input: ProfileInput,
): Promise<NutritionPreview> {
  const payload = await apiRequest<DataEnvelope<NutritionPreview>>(
    "/v1/goals/preview",
    {
      method: "POST",
      body: JSON.stringify(input),
    },
  );

  return payload.data;
}

export async function saveMyProfile(input: ProfileInput): Promise<{
  user: SavedUser;
  goal: NutritionGoal;
}> {
  const payload = await apiRequest<
    DataEnvelope<{ user: SavedUser; goal: NutritionGoal }>
  >("/v1/me/profile", {
    method: "PUT",
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function fetchCurrentGoal(): Promise<NutritionGoal | null> {
  const payload = await apiRequest<DataEnvelope<NutritionGoal | null>>(
    "/v1/goals/current",
  );

  return payload.data;
}

export const ACTIVITY_OPTIONS: Array<{
  value: ActivityLevel;
  label: string;
}> = [
  { value: "sedentary", label: "В основном сижу" },
  { value: "light", label: "Лёгкая активность" },
  { value: "moderate", label: "Тренировки 3–5 раз в неделю" },
  { value: "high", label: "Высокая активность" },
];

export const GOAL_OPTIONS: Array<{
  value: NutritionGoalType;
  label: string;
  description: string;
}> = [
  {
    value: "lose_weight",
    label: "Похудение",
    description: "Умеренный дефицит калорий",
  },
  {
    value: "gain_muscle",
    label: "Набор массы",
    description: "Небольшой профицит калорий",
  },
];
