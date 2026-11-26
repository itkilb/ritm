import { describe, expect, it } from "vitest";

import {
  caloriePreviewSchema,
  emptyExercise,
  emptySet,
  emptyWorkoutValues,
  formToCaloriePreviewInput,
  formToWorkoutInput,
  switchWorkoutType,
  workoutFormSchema,
  workoutToFormValues,
  workoutToRestoreInput,
  type WorkoutFormValues,
} from "@/lib/workout-form";
import {
  runWorkoutFixture,
  workoutFixture,
} from "@/test/workout-fixtures";
import { TEST_TODAY } from "@/test/fixtures";

function values(
  overrides: Partial<WorkoutFormValues> = {},
): WorkoutFormValues {
  return {
    ...emptyWorkoutValues("strength", TEST_TODAY),
    duration_minutes: "55",
    exercises: [
      {
        name: "Жим лёжа",
        mode: "weighted",
        sets: [{ weight_kg: "60", reps: "10", seconds: "" }],
      },
    ],
    ...overrides,
  };
}

function validStrength(): WorkoutFormValues {
  return values();
}

describe("workout form schema", () => {
  it("accepts a valid weighted strength form and maps seconds and nested sets", () => {
    const result = workoutFormSchema.safeParse(validStrength());
    expect(result.success).toBe(true);

    if (!result.success) {
      return;
    }

    expect(formToWorkoutInput(result.data)).toEqual({
      type: "strength",
      date: TEST_TODAY,
      name: "Силовая тренировка",
      duration_seconds: 3300,
      calories_mode: "auto",
      feeling: null,
      note: null,
      exercises: [
        {
          name: "Жим лёжа",
          mode: "weighted",
          sets: [{ weight_kg: 60, reps: 10 }],
        },
      ],
    });
  });

  it("validates each accounting mode and rejects missing required fields", () => {
    const cases: Array<[WorkoutFormValues, string]> = [
      [
        values({
          exercises: [
            {
              name: "Жим",
              mode: "weighted",
              sets: [{ weight_kg: "", reps: "10", seconds: "" }],
            },
          ],
        }),
        "weight_kg",
      ],
      [
        values({
          exercises: [
            {
              name: "Жим",
              mode: "weighted",
              sets: [{ weight_kg: "60", reps: "", seconds: "" }],
            },
          ],
        }),
        "reps",
      ],
      [
        values({
          exercises: [
            {
              name: "Планка",
              mode: "time",
              sets: [{ weight_kg: "", reps: "", seconds: "" }],
            },
          ],
        }),
        "seconds",
      ],
      [
        values({
          exercises: [
            {
              name: "Подтягивания",
              mode: "reps",
              sets: [{ weight_kg: "", reps: "0", seconds: "" }],
            },
          ],
        }),
        "reps",
      ],
      [
        values({
          exercises: [{ name: "", mode: "reps", sets: [emptySet("reps")] }],
        }),
        "name",
      ],
      [values({ exercises: [] }), "exercises"],
      [values({ duration_minutes: "0" }), "duration_minutes"],
      [values({ duration_minutes: "1441" }), "duration_minutes"],
    ];

    for (const [formValues, expectedPath] of cases) {
      const result = workoutFormSchema.safeParse(formValues);
      expect(result.success).toBe(false);

      if (!result.success) {
        expect(
          result.error.issues.some((issue) =>
            issue.path.join(".").includes(expectedPath),
          ),
          `${expectedPath} in ${JSON.stringify(result.error.issues)}`,
        ).toBe(true);
      }
    }
  });

  it("accepts bodyweight additional weight as optional and time-only sets", () => {
    const bodyweight = values({
      exercises: [
        {
          name: "Отжимания",
          mode: "bodyweight",
          sets: [{ weight_kg: "", reps: "12", seconds: "" }],
        },
        {
          name: "Планка",
          mode: "time",
          sets: [{ weight_kg: "", reps: "", seconds: "60" }],
        },
      ],
    });

    const result = workoutFormSchema.safeParse(bodyweight);
    expect(result.success).toBe(true);

    if (result.success) {
      const input = formToWorkoutInput(result.data);
      expect(input.exercises).toEqual([
        {
          name: "Отжимания",
          mode: "bodyweight",
          sets: [{ weight_kg: 0, reps: 12 }],
        },
        {
          name: "Планка",
          mode: "time",
          sets: [{ seconds: 60 }],
        },
      ]);
    }
  });

  it("requires exactly the cardio fields for the selected input mode", () => {
    const run = values({
      type: "run",
      name: "Пробежка",
      input_mode: "distance",
      distance_km: "5.2",
      extra_seconds: "30",
      exercises: [],
    });

    const distanceResult = workoutFormSchema.safeParse(run);
    expect(distanceResult.success).toBe(true);

    if (distanceResult.success) {
      const input = formToWorkoutInput(distanceResult.data);
      expect(input).toMatchObject({
        type: "run",
        duration_seconds: 3330,
        input_mode: "distance",
        distance_km: 5.2,
        incline_percent: null,
        pulse: null,
        exercises: [],
      });
      expect(input.speed_kmh).toBeUndefined();
    }

    const speed = {
      ...run,
      input_mode: "speed" as const,
      distance_km: "",
      speed_kmh: "10",
    };

    const speedResult = workoutFormSchema.safeParse(speed);
    expect(speedResult.success).toBe(true);

    if (speedResult.success) {
      const input = formToWorkoutInput(speedResult.data);
      expect(input).toMatchObject({
        input_mode: "speed",
        speed_kmh: 10,
      });
      expect(input.distance_km).toBeUndefined();
    }

    expect(
      workoutFormSchema.safeParse({ ...run, distance_km: "" }).success,
    ).toBe(false);
    expect(
      workoutFormSchema.safeParse({
        ...speed,
        speed_kmh: "",
      }).success,
    ).toBe(false);
    expect(
      workoutFormSchema.safeParse({
        ...run,
        type: "cardio",
        incline_percent: "2",
      }).success,
    ).toBe(false);
  });

  it("applies the 1000 km cap to the rounded speed-derived distance", () => {
    const base = values({
      type: "run",
      name: "Пробежка",
      duration_minutes: "300",
      extra_seconds: "0",
      input_mode: "speed",
      distance_km: "",
      speed_kmh: "200",
      exercises: [],
    });

    expect(workoutFormSchema.safeParse(base).success).toBe(true);

    const overLimit = workoutFormSchema.safeParse({
      ...base,
      duration_minutes: "1440",
    });

    expect(overLimit.success).toBe(false);

    if (!overLimit.success) {
      expect(
        overLimit.error.issues.some(
          (issue) =>
            issue.path.join(".") === "speed_kmh" &&
            issue.message.includes("1000"),
        ),
      ).toBe(true);
    }
  });

  it("preserves non-whole seconds when converting a saved workout", () => {
    const form = workoutToFormValues(workoutFixture({ duration_seconds: 3331 }));
    expect(form.duration_minutes).toBe("55");
    expect(form.extra_seconds).toBe("31");

    expect(
      formToWorkoutInput({
        ...form,
        duration_minutes: "55",
        extra_seconds: "31",
      }).duration_seconds,
    ).toBe(3331);

    const runForm = workoutToFormValues(runWorkoutFixture());
    expect(runForm.distance_km).toBe("5.2");
    expect(runForm.pulse).toBe("145");
    expect(runForm.incline_percent).toBe("2.5");
    expect(runForm.input_mode).toBe("distance");
  });

  it("switches type without leaking exercises or cardio values", () => {
    const strength = validStrength();
    const cardio = switchWorkoutType(
      {
        ...strength,
        distance_km: "5",
        pulse: "140",
      },
      "cardio",
    );

    expect(cardio.exercises).toEqual([]);
    expect(cardio.distance_km).toBe("");
    expect(cardio.pulse).toBe("");
    expect(cardio.name).toBe("Кардиотренировка");
    expect(cardio.duration_minutes).toBe("55");

    const backToStrength = switchWorkoutType(cardio, "strength");
    expect(backToStrength.exercises).toEqual([emptyExercise()]);
    expect(backToStrength.distance_km).toBe("");
  });
});

describe("task G calorie inputs", () => {
  it("starts new workouts in automatic mode and keeps the manual value across switches", () => {
    const values = emptyWorkoutValues("strength", TEST_TODAY);
    expect(values.calories_mode).toBe("auto");
    expect(values.calorie_weight_kg).toBe("");

    const manual = { ...values, calories_mode: "manual" as const, calories: "123" };
    const switched = switchWorkoutType(manual, "run");
    expect(switched.calories_mode).toBe("manual");
    expect(switched.calories).toBe("123");
  });

  it("sends an explicit manual mode and distinguishes null from a valid 0", () => {
    const base = values({
      calories_mode: "manual",
      calories: "0",
    });
    const parsed = workoutFormSchema.parse(base);
    expect(formToWorkoutInput(parsed)).toMatchObject({
      calories_mode: "manual",
      calories: 0,
    });

    const blank = workoutFormSchema.parse(
      values({ calories_mode: "manual", calories: "" }),
    );
    expect(formToWorkoutInput(blank)).toMatchObject({
      calories_mode: "manual",
      calories: null,
    });
  });

  it("sends a weight override only when the auto weight field is filled", () => {
    const auto = workoutFormSchema.parse(
      values({ calories_mode: "auto", calorie_weight_kg: "77.5" }),
    );
    expect(formToWorkoutInput(auto)).toMatchObject({
      calories_mode: "auto",
      calorie_weight_kg: 77.5,
    });
    expect(formToWorkoutInput(auto)).not.toHaveProperty("calories");

    const fromProfile = workoutFormSchema.parse(
      values({ calories_mode: "auto", calorie_weight_kg: "" }),
    );
    expect(formToWorkoutInput(fromProfile)).not.toHaveProperty(
      "calorie_weight_kg",
    );
  });

  it("accepts stationary cardio without a fictitious distance", () => {
    const cardio = values({
      type: "cardio",
      name: "Велотренажёр",
      duration_minutes: "30",
      exercises: [],
      activity_kind: "stationary_bike",
      power_watts: "100",
      distance_km: "",
      speed_kmh: "",
    });

    const parsed = workoutFormSchema.safeParse(cardio);
    expect(parsed.success).toBe(true);
  });

  it("requires an incline before confirming an estimate without incline", () => {
    const run = values({
      type: "run",
      name: "Бег",
      duration_minutes: "30",
      exercises: [],
      distance_km: "5",
      incline_percent: "",
      incline_ignored: true,
    });

    const parsed = workoutFormSchema.safeParse(run);
    expect(parsed.success).toBe(false);
    if (!parsed.success) {
      expect(
        parsed.error.issues.some((issue) =>
          issue.path.includes("incline_ignored"),
        ),
      ).toBe(true);
    }
  });

  it("keeps the saved weight out of the edit payload and restores it on undo", () => {
    const auto = workoutFixture({
      calories_mode: "auto",
      calories_status: "calculated",
      calories: 332,
      active_kcal: 332,
      gross_kcal: 372,
      met_code: "12050",
      met_value: 9.3,
      weight_kg: 80,
      weight_source: "goal_snapshot",
    });

    const formValues = workoutToFormValues(auto);
    expect(formValues.calorie_weight_kg).toBe("");
    expect(formValues.calories_mode).toBe("auto");

    const input = formToWorkoutInput(formValues);
    expect(input).not.toHaveProperty("calorie_weight_kg");

    expect(workoutToRestoreInput(auto)).toMatchObject({
      calories_mode: "auto",
      calorie_weight_kg: 80,
      calories_recalculate: true,
    });
  });

  it("builds a preview payload without name and exercises and keeps an unfinished draft valid", () => {
    const draft = values({
      name: "",
      exercises: [
        {
          name: "",
          mode: "weighted",
          sets: [{ weight_kg: "0", reps: "10", seconds: "" }],
        },
      ],
      duration_minutes: "45",
      strength_character: "ordinary",
    });

    expect(workoutFormSchema.safeParse(draft).success).toBe(false);
    expect(caloriePreviewSchema.safeParse(draft).success).toBe(true);

    const payload = formToCaloriePreviewInput(draft);
    expect(payload).toMatchObject({
      type: "strength",
      date: TEST_TODAY,
      duration_seconds: 2700,
      calories_mode: "auto",
      strength_character: "ordinary",
    });
    expect(payload).not.toHaveProperty("name");
    expect(payload).not.toHaveProperty("exercises");
    expect(payload).not.toHaveProperty("calorie_weight_kg");
  });

  it("transports the exact saved weight for preview and restore without rounding", () => {
    const exact = values({
      calorie_weight_kg: "80.265",
      strength_character: "ordinary",
    });
    const parsed = workoutFormSchema.safeParse(exact);
    expect(parsed.success).toBe(true);

    const preview = formToCaloriePreviewInput(exact);
    expect(preview.calorie_weight_kg).toBe(80.265);

    const auto = workoutFixture({
      calories_mode: "auto",
      calories_status: "calculated",
      calories: 150,
      weight_kg: 80.265,
      weight_source: "goal_snapshot",
    });
    expect(workoutToRestoreInput(auto)).toMatchObject({
      calorie_weight_kg: 80.265,
      calories_recalculate: true,
    });
  });

  it("restores a manual 0 as manual, not as an automatic estimate", () => {
    const manual = workoutFixture({
      calories_mode: "manual",
      calories_status: "manual",
      calories: 0,
    });

    expect(workoutToRestoreInput(manual)).toMatchObject({
      calories_mode: "manual",
      calories: 0,
    });
  });
});
