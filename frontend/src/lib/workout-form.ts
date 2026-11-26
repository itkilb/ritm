import { z } from "zod";

import { decimalParts, toDecimal } from "@/lib/entry-form";
import { distanceFromSpeed } from "@/lib/workout-math";
import {
  type ActivityKind,
  type CaloriesMode,
  type CardioInputMode,
  type CardioIntensity,
  type ExerciseMode,
  type RunSurface,
  type StrengthCharacter,
  type Workout,
  type WorkoutCaloriePreviewInput,
  type WorkoutExerciseInput,
  type WorkoutFeeling,
  type WorkoutInput,
  type WorkoutType,
  type WeightSource,
} from "@/lib/workout-api";

export type WorkoutSetForm = {
  weight_kg: string;
  reps: string;
  seconds: string;
};

export type WorkoutExerciseForm = {
  exercise_id?: string | null;
  catalog_optional?: boolean;
  name: string;
  mode: ExerciseMode;
  sets: WorkoutSetForm[];
};

export type WorkoutFormValues = {
  type: WorkoutType;
  name: string;
  date: string;
  duration_minutes: string;
  extra_seconds: string;
  calories_mode: CaloriesMode;
  calories: string;
  calorie_weight_kg: string;
  strength_character: "" | StrengthCharacter;
  activity_kind: "" | ActivityKind;
  cardio_intensity: "" | CardioIntensity;
  power_watts: string;
  incline_ignored: boolean;
  run_surface: RunSurface;
  feeling: "" | WorkoutFeeling;
  note: string;
  input_mode: CardioInputMode;
  distance_km: string;
  speed_kmh: string;
  pulse: string;
  incline_percent: string;
  exercises: WorkoutExerciseForm[];
};

export const EXERCISE_MODE_LABELS: Record<ExerciseMode, string> = {
  weighted: "Вес и повторения",
  reps: "Повторения",
  time: "Время",
  bodyweight: "Свой вес + отягощение",
};

export const WORKOUT_TYPE_LABELS: Record<WorkoutType, string> = {
  strength: "Силовая",
  run: "Бег",
  cardio: "Другое кардио",
};

export const FEELING_LABELS: Record<WorkoutFeeling, string> = {
  easy: "Легко",
  comfortable: "Комфортно",
  hard: "Непросто",
};

export const DEFAULT_WORKOUT_NAMES: Record<WorkoutType, string> = {
  strength: "Силовая тренировка",
  run: "Пробежка",
  cardio: "Кардиотренировка",
};

export const CALORIES_MODE_LABELS: Record<CaloriesMode, string> = {
  auto: "Рассчитать автоматически",
  manual: "Указать вручную",
};

export const STRENGTH_CHARACTER_OPTIONS: Array<{
  value: StrengthCharacter;
  label: string;
  description: string;
}> = [
  {
    value: "ordinary",
    label: "Обычная силовая",
    description: "Умеренная нагрузка, обычные паузы между подходами",
  },
  {
    value: "vigorous",
    label: "Интенсивная силовая",
    description: "Высокая нагрузка, тяжёлые подходы и короткие паузы",
  },
  {
    value: "circuit_moderate",
    label: "Круговая, умеренная",
    description: "Круговая тренировка с умеренной нагрузкой",
  },
];

export const STRENGTH_CHARACTER_LABELS: Record<StrengthCharacter, string> =
  Object.fromEntries(
    STRENGTH_CHARACTER_OPTIONS.map((item) => [item.value, item.label]),
  ) as Record<StrengthCharacter, string>;

export const ACTIVITY_KIND_OPTIONS: Array<{
  value: ActivityKind;
  label: string;
  description: string;
}> = [
  {
    value: "outdoor_bike",
    label: "Велосипед на улице",
    description: "Без электропомощи, ровная дорога",
  },
  {
    value: "stationary_bike",
    label: "Велотренажёр",
    description: "Мощность или общая тренировка",
  },
  { value: "elliptical", label: "Эллипс", description: "Умеренная или интенсивная нагрузка" },
  { value: "other", label: "Другое", description: "Плавание и другое кардио — без авторасчёта" },
];

export const ACTIVITY_KIND_LABELS: Record<ActivityKind, string> =
  Object.fromEntries(
    ACTIVITY_KIND_OPTIONS.map((item) => [item.value, item.label]),
  ) as Record<ActivityKind, string>;

export const CARDIO_INTENSITY_LABELS: Record<CardioIntensity, string> = {
  easy: "Лёгкая",
  moderate: "Умеренная",
  vigorous: "Интенсивная",
  general: "Общая тренировка",
};

export const RUN_SURFACE_LABELS: Record<RunSurface, string> = {
  outdoor_flat: "Ровная поверхность на улице",
  treadmill: "Беговая дорожка",
};

export const WEIGHT_SOURCE_LABELS: Record<WeightSource, string> = {
  goal_snapshot: "вес из цели на дату",
  current_profile_fallback: "текущий вес профиля",
  manual_override: "вес указан для этой тренировки",
  saved_snapshot: "вес из сохранённой тренировки",
  weight_entry: "вес из журнала замеров",
};

function blank(value: number | null | undefined): string {
  return value === null || value === undefined ? "" : String(value);
}

function optionalInteger(
  label: string,
  minimum: number,
  maximum: number,
): z.ZodString {
  return z
    .string()
    .trim()
    .refine(
      (value) =>
        value === "" ||
        (/^\d+$/.test(value) &&
          Number(value) >= minimum &&
          Number(value) <= maximum),
      `${label}: целое число от ${minimum} до ${maximum} или пустое поле.`,
    );
}

function optionalDecimal(
  label: string,
  minimum: number,
  maximum: number,
  scale: number,
): z.ZodString {
  return z
    .string()
    .trim()
    .refine((value) => {
      if (value === "") {
        return true;
      }

      const parts = decimalParts(value);

      return (
        parts !== null &&
        parts.value >= minimum &&
        parts.value <= maximum &&
        parts.fractionDigits <= scale
      );
    }, `${label}: число от ${minimum} до ${maximum}, не более ${scale} знаков после запятой.`);
}

function optionalCalorieWeight(): z.ZodString {
  return z
    .string()
    .trim()
    .refine((value) => {
      if (value === "") {
        return true;
      }

      const parts = decimalParts(value);

      return parts !== null && parts.value >= 35 && parts.value <= 300;
    }, "Вес для расчёта: число от 35 до 300.");
}

function isCalendarDate(value: string): boolean {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

  if (!match) {
    return false;
  }

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const parsed = new Date(Date.UTC(year, month - 1, day));

  return (
    parsed.getUTCFullYear() === year &&
    parsed.getUTCMonth() === month - 1 &&
    parsed.getUTCDate() === day
  );
}

const dateField = z
  .string()
  .refine(isCalendarDate, "Укажи действительную дату тренировки.");

const durationMinutesField = z
  .string()
  .trim()
  .refine(
    (value) =>
      /^\d+$/.test(value) && Number(value) >= 1 && Number(value) <= 1440,
    "Длительность: целое число минут от 1 до 1440.",
  );

const extraSecondsField = z
  .string()
  .trim()
  .refine(
    (value) =>
      /^\d+$/.test(value) && Number(value) >= 0 && Number(value) <= 59,
    "Дополнительные секунды: от 0 до 59.",
  );

const setSchema = z.object({
  weight_kg: z.string(),
  reps: z.string(),
  seconds: z.string(),
});

const exerciseSchema = z
  .object({
  exercise_id: z.string().nullable().optional(),
  catalog_optional: z.boolean().optional(),
  name: z
    .string()
    .trim()
    .min(1, "Укажи название упражнения.")
    .max(100, "Название упражнения — не более 100 символов."),
  mode: z.enum(["weighted", "reps", "time", "bodyweight"]),
  sets: z.array(setSchema).min(1, "Добавь хотя бы один подход.").max(50),
  })
  .superRefine((exercise, context) => {
    if (exercise.catalog_optional === false && exercise.exercise_id == null) {
      context.addIssue({
        code: "custom",
        path: ["exercise_id"],
        message: "Выбери упражнение из справочника или создай личное.",
      });
    }
  });

export const workoutFormSchema = z
  .object({
    type: z.enum(["strength", "run", "cardio"]),
    name: z
      .string()
      .trim()
      .min(1, "Добавь название тренировки.")
      .max(100, "Название — не более 100 символов."),
    date: dateField,
    duration_minutes: durationMinutesField,
    extra_seconds: extraSecondsField,
    calories_mode: z.enum(["auto", "manual"]),
    calories: optionalInteger("Калории", 0, 20_000),
    calorie_weight_kg: optionalCalorieWeight(),
    strength_character: z.union([
      z.literal(""),
      z.enum(["ordinary", "vigorous", "circuit_moderate"]),
    ]),
    activity_kind: z.union([
      z.literal(""),
      z.enum(["outdoor_bike", "stationary_bike", "elliptical", "other"]),
    ]),
    cardio_intensity: z.union([
      z.literal(""),
      z.enum(["easy", "moderate", "vigorous", "general"]),
    ]),
    power_watts: optionalInteger("Мощность", 0, 5000),
    incline_ignored: z.boolean(),
    run_surface: z.enum(["outdoor_flat", "treadmill"]),
    feeling: z.union([
      z.literal(""),
      z.enum(["easy", "comfortable", "hard"]),
    ]),
    note: z.string().max(2000, "Заметка — не более 2000 символов."),
    input_mode: z.enum(["distance", "speed"]),
    distance_km: optionalDecimal("Дистанция", 0.001, 1000, 3),
    speed_kmh: optionalDecimal("Скорость", 0.1, 200, 2),
    pulse: optionalInteger("Пульс", 30, 250),
    incline_percent: optionalDecimal("Уклон", 0, 40, 1),
    exercises: z.array(exerciseSchema).max(50),
  })
  .superRefine((values, context) => {
    const totalSeconds =
      Number(values.duration_minutes) * 60 + Number(values.extra_seconds || 0);

    if (totalSeconds < 60 || totalSeconds > 86_400) {
      context.addIssue({
        code: "custom",
        path: ["duration_minutes"],
        message: "Длительность должна быть от 1 минуты до 24 часов.",
      });
    }

    if (values.type === "strength") {
      if (values.exercises.length === 0) {
        context.addIssue({
          code: "custom",
          path: ["exercises"],
          message: "Добавь хотя бы одно упражнение.",
        });
      }

      values.exercises.forEach((exercise, exerciseIndex) => {
        exercise.sets.forEach((set, setIndex) => {
          const path = ["exercises", exerciseIndex, "sets", setIndex] as const;

          if (exercise.mode === "weighted") {
            if (set.weight_kg.trim() === "") {
              context.addIssue({
                code: "custom",
                path: [...path, "weight_kg"],
                message: "Укажи вес.",
              });
            } else {
              const weight = decimalParts(set.weight_kg);
              if (!weight || weight.value < 0 || weight.value > 1000 || weight.fractionDigits > 2) {
                context.addIssue({
                  code: "custom",
                  path: [...path, "weight_kg"],
                  message: "Вес: от 0 до 1000, не более 2 знаков после запятой.",
                });
              }
            }
          }

          if (exercise.mode === "bodyweight" && set.weight_kg.trim() !== "") {
            const weight = decimalParts(set.weight_kg);
            if (!weight || weight.value < 0 || weight.value > 1000 || weight.fractionDigits > 2) {
              context.addIssue({
                code: "custom",
                path: [...path, "weight_kg"],
                message: "Доп. вес: от 0 до 1000, не более 2 знаков после запятой.",
              });
            }
          }

          if (exercise.mode !== "time") {
            if (
              !/^\d+$/.test(set.reps.trim()) ||
              Number(set.reps) < 1 ||
              Number(set.reps) > 1000
            ) {
              context.addIssue({
                code: "custom",
                path: [...path, "reps"],
                message: "Повторения: целое число от 1 до 1000.",
              });
            }
          }

          if (exercise.mode === "time") {
            if (
              !/^\d+$/.test(set.seconds.trim()) ||
              Number(set.seconds) < 1 ||
              Number(set.seconds) > 86_400
            ) {
              context.addIssue({
                code: "custom",
                path: [...path, "seconds"],
                message: "Время подхода: целое число секунд от 1 до 86400.",
              });
            }
          }
        });
      });

      return;
    }

    if (values.type === "cardio" && values.incline_percent.trim() !== "") {
      context.addIssue({
        code: "custom",
        path: ["incline_percent"],
        message: "Уклон указывается только для бега.",
      });
    }

    const distance = values.distance_km.trim();
    const speed = values.speed_kmh.trim();

    if (distance !== "" && speed !== "") {
      context.addIssue({
        code: "custom",
        path: ["distance_km"],
        message: "Укажи либо дистанцию, либо скорость, не оба поля.",
      });
    }

    if (values.input_mode === "speed" && distance !== "") {
      context.addIssue({
        code: "custom",
        path: ["distance_km"],
        message: "При вводе скорости дистанция рассчитывается сервером.",
      });
    }

    if (values.input_mode === "distance" && speed !== "") {
      context.addIssue({
        code: "custom",
        path: ["speed_kmh"],
        message: "При вводе дистанции скорость рассчитывается сервером.",
      });
    }

    if (values.type === "run") {
      if (values.input_mode === "distance" && distance === "") {
        context.addIssue({
          code: "custom",
          path: ["distance_km"],
          message: "Укажи дистанцию.",
        });
      }

      if (values.input_mode === "speed" && speed === "") {
        context.addIssue({
          code: "custom",
          path: ["speed_kmh"],
          message: "Укажи скорость.",
        });
      }
    }

    if (values.input_mode === "speed" && speed !== "") {
      const parsed = decimalParts(speed);
      const calculatedDistance =
        parsed !== null ? distanceFromSpeed(parsed.value, totalSeconds) : null;

      if (calculatedDistance !== null && calculatedDistance > 1000) {
        context.addIssue({
          code: "custom",
          path: ["speed_kmh"],
          message: "Расчётная дистанция не может превышать 1000 км.",
        });
      }
    }

    if (values.incline_ignored) {
      const incline = decimalParts(values.incline_percent);

      if (values.type !== "run" || incline === null || incline.value <= 0) {
        context.addIssue({
          code: "custom",
          path: ["incline_ignored"],
          message: "Оценить без учёта уклона можно только при ненулевом уклоне.",
        });
      }
    }
  });

export const caloriePreviewSchema = z
  .object({
    type: z.enum(["strength", "run", "cardio"]),
    date: dateField,
    duration_minutes: durationMinutesField,
    extra_seconds: extraSecondsField,
    calories_mode: z.enum(["auto", "manual"]),
    calorie_weight_kg: optionalCalorieWeight(),
    strength_character: z.union([
      z.literal(""),
      z.enum(["ordinary", "vigorous", "circuit_moderate"]),
    ]),
    activity_kind: z.union([
      z.literal(""),
      z.enum(["outdoor_bike", "stationary_bike", "elliptical", "other"]),
    ]),
    cardio_intensity: z.union([
      z.literal(""),
      z.enum(["easy", "moderate", "vigorous", "general"]),
    ]),
    power_watts: optionalInteger("Мощность", 0, 5000),
    incline_ignored: z.boolean(),
    run_surface: z.enum(["outdoor_flat", "treadmill"]),
    input_mode: z.enum(["distance", "speed"]),
    distance_km: optionalDecimal("Дистанция", 0.001, 1000, 3),
    speed_kmh: optionalDecimal("Скорость", 0.1, 200, 2),
    incline_percent: optionalDecimal("Уклон", 0, 40, 1),
  })
  .superRefine((values, context) => {
    const totalSeconds =
      Number(values.duration_minutes) * 60 + Number(values.extra_seconds || 0);

    if (totalSeconds < 60 || totalSeconds > 86_400) {
      context.addIssue({
        code: "custom",
        path: ["duration_minutes"],
        message: "Длительность должна быть от 1 минуты до 24 часов.",
      });
    }

    const distance = values.distance_km.trim();
    const speed = values.speed_kmh.trim();

    if (distance !== "" && speed !== "") {
      context.addIssue({
        code: "custom",
        path: ["distance_km"],
        message: "Укажи либо дистанцию, либо скорость, не оба поля.",
      });
    }

    if (values.input_mode === "speed" && distance !== "") {
      context.addIssue({
        code: "custom",
        path: ["distance_km"],
        message: "При вводе скорости дистанция рассчитывается сервером.",
      });
    }

    if (values.input_mode === "distance" && speed !== "") {
      context.addIssue({
        code: "custom",
        path: ["speed_kmh"],
        message: "При вводе дистанции скорость рассчитывается сервером.",
      });
    }

    if (values.input_mode === "speed" && speed !== "") {
      const parsed = decimalParts(speed);
      const calculatedDistance =
        parsed !== null ? distanceFromSpeed(parsed.value, totalSeconds) : null;

      if (calculatedDistance !== null && calculatedDistance > 1000) {
        context.addIssue({
          code: "custom",
          path: ["speed_kmh"],
          message: "Расчётная дистанция не может превышать 1000 км.",
        });
      }
    }

    if (values.incline_ignored) {
      const incline = decimalParts(values.incline_percent);

      if (values.type !== "run" || incline === null || incline.value <= 0) {
        context.addIssue({
          code: "custom",
          path: ["incline_ignored"],
          message: "Оценить без учёта уклона можно только при ненулевом уклоне.",
        });
      }
    }

  });

export function emptySet(mode: ExerciseMode = "weighted"): WorkoutSetForm {
  return {
    weight_kg: mode === "weighted" || mode === "bodyweight" ? "0" : "",
    reps: mode === "time" ? "" : "10",
    seconds: mode === "time" ? "30" : "",
  };
}

export function emptyExercise(): WorkoutExerciseForm {
  return {
    exercise_id: null,
    catalog_optional: false,
    name: "",
    mode: "weighted",
    sets: [emptySet("weighted")],
  };
}

export function defaultWorkoutName(type: WorkoutType): string {
  return DEFAULT_WORKOUT_NAMES[type];
}

export function emptyWorkoutValues(
  type: WorkoutType,
  date: string,
): WorkoutFormValues {
  return {
    type,
    name: defaultWorkoutName(type),
    date,
    duration_minutes: "",
    extra_seconds: "0",
    calories_mode: "auto",
    calories: "",
    calorie_weight_kg: "",
    strength_character: "",
    activity_kind: "",
    cardio_intensity: "",
    power_watts: "",
    incline_ignored: false,
    run_surface: "outdoor_flat",
    feeling: "",
    note: "",
    input_mode: "distance",
    distance_km: "",
    speed_kmh: "",
    pulse: "",
    incline_percent: "",
    exercises: type === "strength" ? [emptyExercise()] : [],
  };
}

export function workoutToFormValues(
  workout: Workout,
  dateOverride?: string,
): WorkoutFormValues {
  return {
    type: workout.type,
    name: workout.name,
    date: dateOverride ?? workout.date,
    duration_minutes: String(Math.floor(workout.duration_seconds / 60)),
    extra_seconds: String(workout.duration_seconds % 60),
    calories_mode: workout.calories_mode,
    calories: workout.calories === null ? "" : String(workout.calories),
    // Blank means "use the saved/server basis"; only a typed value overrides the snapshot.
    calorie_weight_kg: "",
    strength_character: workout.strength_character ?? "",
    activity_kind: workout.activity_kind ?? "",
    cardio_intensity: workout.cardio_intensity ?? "",
    power_watts: blank(workout.power_watts),
    incline_ignored: workout.incline_ignored,
    run_surface: workout.run_surface ?? "outdoor_flat",
    feeling: workout.feeling ?? "",
    note: workout.note ?? "",
    input_mode: "distance",
    distance_km: blank(workout.distance_km),
    speed_kmh: "",
    pulse: blank(workout.pulse),
    incline_percent: blank(workout.incline_percent),
    exercises: workout.exercises.map((exercise) => ({
      exercise_id: exercise.exercise_id ?? null,
      catalog_optional: !exercise.exercise_id,
      name: exercise.name,
      mode: exercise.mode,
      sets: exercise.sets.map((set) => ({
        weight_kg: blank(set.weight_kg),
        reps: blank(set.reps),
        seconds: blank(set.seconds),
      })),
    })),
  };
}

function numberOrZero(value: string): number {
  return value.trim() === "" ? 0 : Number(value);
}

export function formToWorkoutInput(
  values: WorkoutFormValues,
  options: { recalculate?: boolean } = {},
): WorkoutInput {
  const durationSeconds =
    Number(values.duration_minutes) * 60 + Number(values.extra_seconds || 0);

  const input: WorkoutInput = {
    type: values.type,
    date: values.date,
    name: values.name.trim(),
    duration_seconds: durationSeconds,
    calories_mode: values.calories_mode,
    feeling: values.feeling === "" ? null : values.feeling,
    note: values.note.trim() === "" ? null : values.note.trim(),
    exercises: [],
  };

  if (values.calories_mode === "manual") {
    input.calories =
      values.calories.trim() === "" ? null : Number(values.calories);
  } else if (values.calorie_weight_kg.trim() !== "") {
    input.calorie_weight_kg = toDecimal(values.calorie_weight_kg);
  }

  if (values.strength_character !== "") {
    input.strength_character = values.strength_character;
  }

  if (values.activity_kind !== "") {
    input.activity_kind = values.activity_kind;
  }

  if (values.cardio_intensity !== "") {
    input.cardio_intensity = values.cardio_intensity;
  }

  if (values.power_watts.trim() !== "") {
    input.power_watts = Number(values.power_watts);
  }

  // Only an explicit recalculation forces the server to replace the saved snapshot.
  if (options.recalculate) {
    input.calories_recalculate = true;
  }

  if (values.type === "strength") {
    input.exercises = values.exercises.map(
      (exercise): WorkoutExerciseInput => ({
        exercise_id: exercise.exercise_id ?? undefined,
        name: exercise.name.trim(),
        mode: exercise.mode,
        sets: exercise.sets.map((set) => {
          if (exercise.mode === "time") {
            return { seconds: Number(set.seconds) };
          }

          if (exercise.mode === "reps") {
            return { reps: Number(set.reps) };
          }

          if (exercise.mode === "bodyweight") {
            return {
              weight_kg: numberOrZero(set.weight_kg),
              reps: Number(set.reps),
            };
          }

          return {
            weight_kg: toDecimal(set.weight_kg),
            reps: Number(set.reps),
          };
        }),
      }),
    );

    return input;
  }

  input.input_mode = values.input_mode;
  input.pulse = values.pulse.trim() === "" ? null : Number(values.pulse);

  if (values.input_mode === "speed") {
    if (values.speed_kmh.trim() !== "") {
      input.speed_kmh = toDecimal(values.speed_kmh);
    }
  } else if (values.distance_km.trim() !== "") {
    input.distance_km = toDecimal(values.distance_km);
  }

  if (values.type === "run") {
    input.incline_percent =
      values.incline_percent.trim() === ""
        ? null
        : toDecimal(values.incline_percent);
    input.run_surface = values.run_surface;
    input.incline_ignored = values.incline_ignored;
  }

  return input;
}

// Preview sends only calorie inputs; name, exercises and sets are not part of the formula.
export function formToCaloriePreviewInput(
  values: WorkoutFormValues,
  options: { recalculate?: boolean } = {},
): WorkoutCaloriePreviewInput {
  const durationSeconds =
    Number(values.duration_minutes) * 60 + Number(values.extra_seconds || 0);

  const input: WorkoutCaloriePreviewInput = {
    type: values.type,
    date: values.date,
    duration_seconds: durationSeconds,
    calories_mode: values.calories_mode,
  };

  if (options.recalculate) {
    input.calories_recalculate = true;
  }

  if (values.calories_mode === "manual") {
    input.calories =
      values.calories.trim() === "" ? null : Number(values.calories);
  } else if (values.calorie_weight_kg.trim() !== "") {
    input.calorie_weight_kg = toDecimal(values.calorie_weight_kg);
  }

  if (values.type === "strength") {
    if (values.strength_character !== "") {
      input.strength_character = values.strength_character;
    }

    return input;
  }

  input.input_mode = values.input_mode;

  if (values.input_mode === "speed") {
    if (values.speed_kmh.trim() !== "") {
      input.speed_kmh = toDecimal(values.speed_kmh);
    }
  } else if (values.distance_km.trim() !== "") {
    input.distance_km = toDecimal(values.distance_km);
  }

  if (values.type === "run") {
    input.incline_percent =
      values.incline_percent.trim() === ""
        ? null
        : toDecimal(values.incline_percent);
    input.run_surface = values.run_surface;
    input.incline_ignored = values.incline_ignored;

    return input;
  }

  if (values.activity_kind !== "") {
    input.activity_kind = values.activity_kind;
  }

  if (values.cardio_intensity !== "") {
    input.cardio_intensity = values.cardio_intensity;
  }

  if (values.power_watts.trim() !== "") {
    input.power_watts = Number(values.power_watts);
  }

  return input;
}

// Undo keeps the original mode, exact weight and manual value (including 0).
export function workoutToRestoreInput(workout: Workout): WorkoutInput {
  const input = formToWorkoutInput(workoutToFormValues(workout));

  if (workout.calories_mode === "auto") {
    input.calories_recalculate = true;

    if (workout.weight_kg !== null) {
      input.calorie_weight_kg = workout.weight_kg;
    }
  } else {
    delete input.calorie_weight_kg;
    input.calories = workout.calories;
  }

  return input;
}

export function workoutServerErrorEntries(
  errors: Record<string, string>,
): Array<{ path: string; message: string }> {
  return Object.entries(errors).map(([path, message]) => ({ path, message }));
}

export function switchWorkoutType(
  values: WorkoutFormValues,
  type: WorkoutType,
): WorkoutFormValues {
  if (type === values.type) {
    return values;
  }

  return {
    ...emptyWorkoutValues(type, values.date),
    name: defaultWorkoutName(type),
    duration_minutes: values.duration_minutes,
    extra_seconds: values.extra_seconds,
    calories_mode: values.calories_mode,
    calories: values.calories,
    calorie_weight_kg: values.calorie_weight_kg,
    feeling: values.feeling,
    note: values.note,
  };
}
