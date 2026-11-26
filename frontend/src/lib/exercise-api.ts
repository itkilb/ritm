import { apiRequest } from "@/lib/api";
import type { ExerciseMode } from "@/lib/workout-api";

export type Exercise = {
  id: string;
  name: string;
  muscle_group: string;
  equipment: string;
  measurement_type: ExerciseMode;
  is_custom: boolean;
};

export type ExerciseFilters = {
  muscle_groups: string[];
  equipment: string[];
  measurement_types: ExerciseMode[];
};

export type ExerciseList = {
  data: Exercise[];
  meta: { filters: ExerciseFilters };
};

export const MUSCLE_GROUPS = [
  "Грудь",
  "Спина",
  "Ноги",
  "Ягодицы",
  "Плечи",
  "Руки",
  "Пресс",
  "Икры",
  "Всё тело",
] as const;

export const EQUIPMENT = [
  "Штанга",
  "Гантели",
  "Тренажёр",
  "Кроссовер",
  "Собственный вес",
  "Гиря",
  "Резинка",
] as const;

export type ExerciseInput = {
  name: string;
  muscle_group: string;
  equipment: string;
  measurement_type: ExerciseMode;
};

export const EXERCISE_MEASUREMENT_LABELS: Record<ExerciseMode, string> = {
  weighted: "Вес и повторения",
  bodyweight: "Свой вес + отягощение",
  reps: "Повторения",
  time: "Время",
};

export async function fetchExercises(): Promise<ExerciseList> {
  return apiRequest<ExerciseList>("/v1/exercises");
}

export async function createExercise(input: ExerciseInput): Promise<Exercise> {
  const payload = await apiRequest<{ data: Exercise }>("/v1/exercises", {
    method: "POST",
    body: JSON.stringify(input),
  });

  return payload.data;
}

export function isMeasurementCompatible(
  measurement: ExerciseMode,
  mode: ExerciseMode,
): boolean {
  if (measurement === "weighted" || measurement === "bodyweight") {
    return mode === "weighted" || mode === "bodyweight";
  }

  return measurement === mode;
}
