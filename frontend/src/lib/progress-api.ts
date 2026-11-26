import { apiRequest } from "@/lib/api";
import type { ActivityKind } from "@/lib/workout-api";

export type ProgressPeriod = "month" | "3months" | "year" | "all";

export type WeightPoint = {
  id: string;
  date: string;
  weight_kg: number;
};

export type WeightAveragePoint = {
  date: string;
  weight_kg: number;
  measurements: number;
};

export type ProgressWeight = {
  entries: WeightPoint[];
  average: WeightAveragePoint[];
  latest: WeightPoint | null;
  change_kg: number | null;
  profile_weight_kg: number | null;
  target_weight_kg: number | null;
};

export type ProgressWeek = {
  week_start: string;
  week_end: string;
  count: number;
  minutes: number;
  calories: number;
  distance_km: number;
};

export type ProgressCardioKind = ActivityKind | "unknown";

export type ProgressCardioKindGroup = {
  activity_kind: ProgressCardioKind;
  count: number;
  minutes: number;
  distance_km: number;
  speed_kmh: number | null;
};

export type ProgressWorkouts = {
  totals: {
    count: number;
    minutes: number;
    duration_seconds: number;
    calories: number;
    uncalculated_count: number;
    distance_km: number;
  };
  weeks: ProgressWeek[];
  by_type: { strength: number; run: number; cardio: number };
  run: {
    count: number;
    minutes: number;
    distance_km: number;
    pace_seconds_per_km: number | null;
    pace: string | null;
  };
  cardio: {
    count: number;
    minutes: number;
    distance_km: number;
    speed_kmh: null;
    kinds: ProgressCardioKindGroup[];
  };
};

export type ExerciseHistoryPoint = {
  date: string;
  workout_id: string;
  workout_name: string;
  exercise_name: string;
  mode: "weighted" | "reps" | "time" | "bodyweight";
  weight_kg: number | null;
  reps: number | null;
  seconds: number | null;
};

export type ExerciseHistory = {
  exercise: {
    id: string;
    name: string;
    muscle_group: string;
    equipment: string;
    measurement_type: "weighted" | "reps" | "time" | "bodyweight";
  };
  points: ExerciseHistoryPoint[];
  summary: {
    count: number;
    best_weight_kg: number | null;
    best_reps: number | null;
    best_seconds: number | null;
  };
};

export type ProgressData = {
  period: ProgressPeriod;
  start_date: string;
  end_date: string;
  timezone: string;
  today: string;
  weight: ProgressWeight;
  workouts: ProgressWorkouts;
  exercise_history: ExerciseHistory | null;
};

export const PROGRESS_PERIOD_LABELS: Record<ProgressPeriod, string> = {
  month: "Месяц",
  "3months": "3 месяца",
  year: "Год",
  all: "Всё время",
};

function progressQuery(
  period: ProgressPeriod,
  date?: string | null,
  exercise?: string | null,
): string {
  const params = new URLSearchParams({ period });

  if (date) {
    params.set("date", date);
  }

  if (exercise) {
    params.set("exercise", exercise);
  }

  return params.toString();
}

export async function fetchProgress(
  period: ProgressPeriod,
  date?: string | null,
  exercise?: string | null,
): Promise<ProgressData> {
  const payload = await apiRequest<{ data: ProgressData }>(
    `/v1/progress?${progressQuery(period, date, exercise)}`,
  );

  return payload.data;
}
