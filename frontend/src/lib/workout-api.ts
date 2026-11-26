import { apiRequest } from "@/lib/api";

export type WorkoutType = "strength" | "run" | "cardio";

export type ExerciseMode = "weighted" | "reps" | "time" | "bodyweight";

export type WorkoutFeeling = "easy" | "comfortable" | "hard";

export type CardioInputMode = "distance" | "speed";

export type CaloriesMode = "auto" | "manual";

export type CaloriesStatus =
  | "calculated"
  | "manual"
  | "legacy"
  | "missing"
  | "unsupported";

export type StrengthCharacter = "ordinary" | "vigorous" | "circuit_moderate";

export type ActivityKind =
  | "outdoor_bike"
  | "stationary_bike"
  | "elliptical"
  | "other";

export type CardioIntensity = "easy" | "moderate" | "vigorous" | "general";

export type RunSurface = "outdoor_flat" | "treadmill";

export type WeightSource =
  | "goal_snapshot"
  | "current_profile_fallback"
  | "manual_override"
  | "saved_snapshot"
  | "weight_entry";

export type WorkoutSet = {
  id: string;
  position: number;
  weight_kg: number | null;
  reps: number | null;
  seconds: number | null;
};

export type WorkoutExercise = {
  id: string;
  exercise_id: string | null;
  name: string;
  mode: ExerciseMode;
  position: number;
  sets: WorkoutSet[];
};

export type CalorieExplanation = {
  mode: CaloriesMode;
  status: CaloriesStatus;
  active_kcal: number | null;
  gross_kcal: number | null;
  met: number | null;
  met_code: string | null;
  category: string | null;
  weight_kg: number | null;
  weight_source: WeightSource | null;
  duration_seconds: number | null;
  catalog: string;
  catalog_version: string | null;
  formula_version: string | null;
  catalog_url: string | null;
  approximate: boolean;
  incline_ignored: boolean;
  reason: string | null;
  warnings: string[];
  notes: string[];
};

export type Workout = {
  id: string;
  date: string;
  type: WorkoutType;
  name: string;
  duration_seconds: number;
  duration_minutes: number;
  calories: number | null;
  calories_mode: CaloriesMode;
  calories_status: CaloriesStatus;
  active_kcal: number | null;
  gross_kcal: number | null;
  calorie_method: string | null;
  met_code: string | null;
  met_value: number | null;
  met_label: string | null;
  weight_kg: number | null;
  weight_source: WeightSource | null;
  catalog_version: string | null;
  formula_version: string | null;
  calorie_approximate: boolean;
  incline_ignored: boolean;
  calorie_inputs: Record<string, unknown>;
  calorie_warnings: string[];
  calorie_explanation: CalorieExplanation;
  activity_kind: ActivityKind | null;
  strength_character: StrengthCharacter | null;
  cardio_intensity: CardioIntensity | null;
  power_watts: number | null;
  feeling: WorkoutFeeling | null;
  note: string | null;
  distance_km: number | null;
  pulse: number | null;
  incline_percent: number | null;
  run_surface: RunSurface | null;
  speed_kmh: number | null;
  pace_seconds_per_km: number | null;
  pace: string | null;
  volume_kg: number;
  exercise_count: number;
  set_count: number;
  exercises: WorkoutExercise[];
};

export type WorkoutDaySummary = {
  date: string;
  count: number;
  minutes: number;
  distance_km: number;
  has_workouts: boolean;
};

export type WorkoutWeekTotals = {
  count: number;
  duration_seconds: number;
  minutes: number;
  calories: number;
  distance_km: number;
  uncalculated_count: number;
};

export type WorkoutDiary = {
  date: string;
  timezone: string;
  today: string;
  week_start: string;
  week_end: string;
  days: WorkoutDaySummary[];
  week: WorkoutWeekTotals;
  workouts: Workout[];
  recent: Workout[];
};

export type WorkoutSetInput = {
  weight_kg?: number | null;
  reps?: number | null;
  seconds?: number | null;
};

export type WorkoutExerciseInput = {
  exercise_id?: string | null;
  name: string;
  mode: ExerciseMode;
  sets: WorkoutSetInput[];
};

export type WorkoutInput = {
  type: WorkoutType;
  date: string;
  name: string;
  duration_seconds: number;
  calories_mode?: CaloriesMode;
  calories?: number | null;
  calorie_weight_kg?: number | null;
  strength_character?: StrengthCharacter | null;
  activity_kind?: ActivityKind | null;
  cardio_intensity?: CardioIntensity | null;
  power_watts?: number | null;
  incline_ignored?: boolean;
  calories_recalculate?: boolean;
  run_surface?: RunSurface | null;
  feeling: WorkoutFeeling | null;
  note: string | null;
  input_mode?: CardioInputMode;
  distance_km?: number | null;
  speed_kmh?: number | null;
  pulse?: number | null;
  incline_percent?: number | null;
  exercises?: WorkoutExerciseInput[];
};

export type WorkoutCaloriePreviewInput = Omit<
  WorkoutInput,
  "name" | "exercises" | "feeling" | "note"
>;

export type WorkoutCaloriePreview = {
  calculation: {
    calories_mode: CaloriesMode;
    calories_status: CaloriesStatus;
    calories: number | null;
    active_kcal: number | null;
    gross_kcal: number | null;
    calorie_method: string | null;
    met_code: string | null;
    met_value: number | null;
    met_label: string | null;
    weight_kg: number | null;
    weight_source: WeightSource | null;
    calorie_inputs: Record<string, unknown>;
    calorie_warnings: string[];
    calorie_approximate: boolean;
    incline_ignored: boolean;
    catalog_version: string | null;
    formula_version: string | null;
  };
  explanation: CalorieExplanation;
};

type DataEnvelope<T> = { data: T };

function dateQuery(value: string | null | undefined): string {
  return value ? `?date=${encodeURIComponent(value)}` : "";
}

export async function fetchWorkoutDiary(
  date?: string | null,
): Promise<WorkoutDiary> {
  const payload = await apiRequest<DataEnvelope<WorkoutDiary>>(
    `/v1/workouts${dateQuery(date)}`,
  );

  return payload.data;
}

export async function fetchWorkout(id: string): Promise<Workout> {
  const payload = await apiRequest<DataEnvelope<Workout>>(
    `/v1/workouts/${encodeURIComponent(id)}`,
  );

  return payload.data;
}

export async function previewWorkout(
  input: WorkoutCaloriePreviewInput,
  workoutId?: string | null,
  signal?: AbortSignal,
): Promise<WorkoutCaloriePreview> {
  const payload = await apiRequest<DataEnvelope<WorkoutCaloriePreview>>(
    "/v1/workouts/preview",
    {
      method: "POST",
      body: JSON.stringify(
        workoutId ? { ...input, workout_id: workoutId } : input,
      ),
      signal,
    },
  );

  return payload.data;
}

export async function createWorkout(input: WorkoutInput): Promise<Workout> {
  const payload = await apiRequest<DataEnvelope<Workout>>("/v1/workouts", {
    method: "POST",
    body: JSON.stringify(input),
  });

  return payload.data;
}

export async function updateWorkout(
  id: string,
  input: WorkoutInput,
): Promise<Workout> {
  const payload = await apiRequest<DataEnvelope<Workout>>(
    `/v1/workouts/${encodeURIComponent(id)}`,
    {
      method: "PUT",
      body: JSON.stringify(input),
    },
  );

  return payload.data;
}

export async function deleteWorkout(id: string): Promise<void> {
  await apiRequest<void>(`/v1/workouts/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}
