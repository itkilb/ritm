import type { ExerciseList } from "@/lib/exercise-api";
import type {
  Workout,
  WorkoutCaloriePreview,
  WorkoutDaySummary,
  WorkoutDiary,
  WorkoutExercise,
  WorkoutSet,
  WorkoutType,
} from "@/lib/workout-api";
import { addIsoDays } from "@/lib/date";
import { TEST_TODAY, TEST_WEEK_START } from "@/test/fixtures";

function set(overrides: Partial<WorkoutSet>): WorkoutSet {
  return {
    id: "set-1",
    position: 0,
    weight_kg: null,
    reps: null,
    seconds: null,
    ...overrides,
  };
}


export function exerciseListFixture(): ExerciseList {
  return {
    data: [
      {
        id: "catalog-squat",
        name: "Приседания",
        muscle_group: "Ноги",
        equipment: "Собственный вес",
        measurement_type: "weighted",
        is_custom: false,
      },
      {
        id: "catalog-plank",
        name: "Планка",
        muscle_group: "Пресс",
        equipment: "Собственный вес",
        measurement_type: "time",
        is_custom: false,
      },
      {
        id: "catalog-bench",
        name: "Жим лёжа",
        muscle_group: "Грудь",
        equipment: "Штанга",
        measurement_type: "weighted",
        is_custom: false,
      },
    ],
    meta: {
      filters: {
        muscle_groups: [
          "Грудь",
          "Спина",
          "Ноги",
          "Ягодицы",
          "Плечи",
          "Руки",
          "Пресс",
          "Икры",
          "Всё тело",
        ],
        equipment: [
          "Штанга",
          "Гантели",
          "Тренажёр",
          "Кроссовер",
          "Собственный вес",
          "Гиря",
          "Резинка",
        ],
        measurement_types: ["weighted", "reps", "time", "bodyweight"],
      },
    },
  };
}

export function workoutFixture(overrides: Partial<Workout> = {}): Workout {
  const exercises: WorkoutExercise[] = [
    {
      id: "exercise-1",
      exercise_id: null,
      name: "Жим лёжа",
      mode: "weighted",
      position: 0,
      sets: [
        set({ id: "set-1", weight_kg: 60, reps: 10 }),
        set({ id: "set-2", position: 1, weight_kg: 62.5, reps: 8 }),
      ],
    },
    {
      id: "exercise-2",
      exercise_id: null,
      name: "Планка",
      mode: "time",
      position: 1,
      sets: [set({ id: "set-3", seconds: 45 })],
    },
  ];

  return {
    id: "workout-1",
    date: TEST_TODAY,
    type: "strength",
    name: "Верх тела",
    duration_seconds: 3300,
    duration_minutes: 55,
    calories: 320,
    calories_mode: "manual",
    calories_status: "manual",
    active_kcal: null,
    gross_kcal: null,
    calorie_method: "manual",
    met_code: null,
    met_value: null,
    met_label: null,
    weight_kg: null,
    weight_source: null,
    catalog_version: null,
    formula_version: null,
    calorie_approximate: false,
    incline_ignored: false,
    calorie_inputs: {},
    calorie_warnings: [],
    calorie_explanation: {
      mode: "manual",
      status: "manual",
      active_kcal: null,
      gross_kcal: null,
      met: null,
      met_code: null,
      category: null,
      weight_kg: null,
      weight_source: null,
      duration_seconds: 3300,
      catalog: "Compendium of Physical Activities 2024",
      catalog_version: null,
      formula_version: null,
      catalog_url: null,
      approximate: false,
      incline_ignored: false,
      reason: null,
      warnings: [],
      notes: [],
    },
    activity_kind: null,
    strength_character: null,
    cardio_intensity: null,
    power_watts: null,
    feeling: "comfortable",
    note: "Хорошая тренировка",
    distance_km: null,
    pulse: null,
    incline_percent: null,
    run_surface: null,
    speed_kmh: null,
    pace_seconds_per_km: null,
    pace: null,
    volume_kg: 1100,
    exercise_count: 2,
    set_count: 3,
    exercises,
    ...overrides,
  };
}

export function runWorkoutFixture(overrides: Partial<Workout> = {}): Workout {
  return workoutFixture({
    id: "workout-run",
    type: "run",
    name: "Пробежка",
    duration_seconds: 1890,
    duration_minutes: 31.5,
    calories: 300,
    distance_km: 5.2,
    pulse: 145,
    incline_percent: 2.5,
    run_surface: "outdoor_flat",
    speed_kmh: 9.9,
    pace_seconds_per_km: 363,
    pace: "6:03",
    volume_kg: 0,
    exercise_count: 0,
    set_count: 0,
    exercises: [],
    ...overrides,
  });
}

export function cardioWorkoutFixture(overrides: Partial<Workout> = {}): Workout {
  return runWorkoutFixture({
    id: "workout-cardio",
    type: "cardio",
    name: "Велосипед",
    distance_km: 12,
    pulse: 135,
    incline_percent: null,
    speed_kmh: 24,
    pace_seconds_per_km: 150,
    pace: "2:30",
    ...overrides,
  });
}

export function workoutDiaryFixture(
  overrides: Partial<WorkoutDiary> = {},
): WorkoutDiary {
  const start = overrides.week_start ?? TEST_WEEK_START;
  const workouts = overrides.workouts ?? [
    workoutFixture(),
    runWorkoutFixture(),
  ];

  const days: WorkoutDaySummary[] = Array.from({ length: 7 }, (_, index) => {
    const date = addIsoDays(start, index);
    const dayWorkouts = workouts.filter((workout) => workout.date === date);

    return {
      date,
      count: dayWorkouts.length,
      minutes: dayWorkouts.reduce(
        (total, workout) => total + workout.duration_seconds / 60,
        0,
      ),
      distance_km: dayWorkouts
        .filter((workout) => workout.type === "run")
        .reduce((total, workout) => total + (workout.distance_km ?? 0), 0),
      has_workouts: dayWorkouts.length > 0,
    };
  });

  return {
    date: TEST_TODAY,
    timezone: "Europe/Moscow",
    today: TEST_TODAY,
    week_start: start,
    week_end: addIsoDays(start, 6),
    days,
    week: {
      count: workouts.length,
      duration_seconds: workouts.reduce(
        (total, workout) => total + workout.duration_seconds,
        0,
      ),
      minutes: workouts.reduce(
        (total, workout) => total + workout.duration_seconds / 60,
        0,
      ),
      calories: workouts.reduce(
        (total, workout) => total + (workout.calories ?? 0),
        0,
      ),
      uncalculated_count: workouts.filter(
        (workout) => workout.calories === null,
      ).length,
      distance_km: workouts
        .filter((workout) => workout.type === "run")
        .reduce((total, workout) => total + (workout.distance_km ?? 0), 0),
    },
    workouts,
    recent: overrides.recent ?? workouts.slice(0, 4),
    ...overrides,
  };
}

export function workoutTypeFixture(type: WorkoutType): Workout {
  if (type === "run") {
    return runWorkoutFixture();
  }

  if (type === "cardio") {
    return cardioWorkoutFixture();
  }

  return workoutFixture();
}

export function calorieExplanationFixture(
  overrides: Partial<Workout["calorie_explanation"]> = {},
): Workout["calorie_explanation"] {
  return {
    mode: "auto",
    status: "calculated",
    active_kcal: 150,
    gross_kcal: 210,
    met: 3.5,
    met_code: "02054",
    category: "Силовая, обычная нагрузка",
    weight_kg: 80,
    weight_source: "goal_snapshot",
    duration_seconds: 2700,
    catalog: "Compendium of Physical Activities 2024",
    catalog_version: "2024/v1",
    formula_version: "met-1-v1",
    catalog_url: "https://pacompendium.com/conditioning-exercise/",
    approximate: false,
    incline_ignored: false,
    reason: null,
    warnings: [],
    notes: ["Расход показан сверх условного покоя; общий расход указан отдельно."],
    ...overrides,
  };
}

export function caloriePreviewFixture(
  overrides: {
    calculation?: Partial<WorkoutCaloriePreview["calculation"]>;
    explanation?: Partial<WorkoutCaloriePreview["explanation"]>;
  } = {},
): WorkoutCaloriePreview {
  const calculation = {
    calories_mode: "auto" as const,
    calories_status: "calculated" as const,
    calories: 150,
    active_kcal: 150,
    gross_kcal: 210,
    calorie_method: "strength_character",
    met_code: "02054",
    met_value: 3.5,
    met_label: "Силовая, обычная нагрузка",
    weight_kg: 80,
    weight_source: "goal_snapshot" as const,
    calorie_inputs: {},
    calorie_warnings: [],
    calorie_approximate: false,
    incline_ignored: false,
    catalog_version: "2024/v1",
    formula_version: "met-1-v1",
  };

  return {
    calculation: { ...calculation, ...(overrides.calculation ?? {}) },
    explanation: calorieExplanationFixture(overrides.explanation),
  };
}
