import { zodResolver } from "@hookform/resolvers/zod";
import { useEffect, useRef, useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";

import { Modal } from "@/components/ui/modal";
import {
  useCreateActivityEntry,
  useCreateFoodEntry,
} from "@/hooks/use-dashboard";
import { ApiError, validationErrors } from "@/lib/api";
import {
  MEAL_TYPES,
  MEAL_TYPE_LABELS,
  type MealType,
} from "@/lib/dashboard-api";
import {
  decimalField,
  integerField,
  toDecimal,
} from "@/lib/entry-form";

type EntryType = "food" | "activity";

const baseSchema = z.object({
  type: z.enum(["food", "activity"]),
  meal_type: z.string(),
  name: z
    .string()
    .trim()
    .min(1, "Добавь название записи.")
    .max(120, "Название не должно превышать 120 символов."),
  calories: decimalField("Калории", 1_000_000, 2),
  protein: z.string(),
  fat: z.string(),
  carbs: z.string(),
  steps: z.string(),
  minutes: z.string(),
  distance_km: z.string(),
});

const entrySchema = baseSchema.superRefine((values, context) => {
  if (
    values.type === "food" &&
    !MEAL_TYPES.includes(values.meal_type as MealType)
  ) {
    context.addIssue({
      code: "custom",
      path: ["meal_type"],
      message: "Выберите приём пищи.",
    });
  }

  const checks =
    values.type === "food"
      ? ([
          ["protein", decimalField("Белки", 1_000_000, 2)],
          ["fat", decimalField("Жиры", 1_000_000, 2)],
          ["carbs", decimalField("Углеводы", 1_000_000, 2)],
        ] as const)
      : ([
          ["steps", integerField("Шаги", 1_000_000)],
          ["minutes", decimalField("Минуты движения", 100_000, 2)],
          ["distance_km", decimalField("Расстояние", 99_999.999, 3)],
        ] as const);

  for (const [field, schema] of checks) {
    const result = schema.safeParse(values[field]);

    if (!result.success) {
      context.addIssue({
        code: "custom",
        path: [field],
        message: result.error.issues[0]?.message ?? "Проверьте значение.",
      });
    }
  }
});

type FormValues = z.infer<typeof entrySchema>;

function emptyValues(
  type: EntryType,
  mealType: MealType | null,
): FormValues {
  return {
    type,
    meal_type: mealType ?? "",
    name: "",
    calories: "",
    protein: "0",
    fat: "0",
    carbs: "0",
    steps: "0",
    minutes: "0",
    distance_km: "0",
  };
}

export function AddEntryDialog({
  date,
  initialType,
  initialMealType = null,
  onClose,
}: {
  date: string;
  initialType: EntryType;
  initialMealType?: MealType | null;
  onClose: () => void;
}) {
  const foodMutation = useCreateFoodEntry();
  const activityMutation = useCreateActivityEntry();
  const [submitError, setSubmitError] = useState<string | null>(null);

  const form = useForm<FormValues>({
    resolver: zodResolver(entrySchema),
    defaultValues: emptyValues(initialType, initialMealType),
    mode: "onTouched",
  });

  const isBusy =
    foodMutation.isPending ||
    activityMutation.isPending ||
    form.formState.isSubmitting;
  const type = form.watch("type");
  const isMountedRef = useRef(true);
  const requestSequence = useRef(0);
  const dateRef = useRef(date);

  useEffect(() => {
    isMountedRef.current = true;

    return () => {
      isMountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    dateRef.current = date;
  }, [date]);

  function handleClose() {
    if (isBusy) {
      return;
    }

    onClose();
  }

  async function onSubmit(values: FormValues) {
    if (foodMutation.isPending || activityMutation.isPending) {
      return;
    }

    form.clearErrors();
    setSubmitError(null);

    // Capture date and request id so a late response cannot reset a newer dialog.
    const targetDate = date;
    const requestId = ++requestSequence.current;
    const requestType = values.type;

    try {
      if (requestType === "food") {
        await foodMutation.mutateAsync({
          date: targetDate,
          name: values.name.trim(),
          meal_type: values.meal_type as MealType,
          calories: toDecimal(values.calories),
          protein: toDecimal(values.protein),
          fat: toDecimal(values.fat),
          carbs: toDecimal(values.carbs),
        });
      } else {
        await activityMutation.mutateAsync({
          date: targetDate,
          name: values.name.trim(),
          calories: toDecimal(values.calories),
          steps: Number(values.steps),
          minutes: toDecimal(values.minutes),
          distance_km: toDecimal(values.distance_km),
        });
      }

      if (
        !isMountedRef.current ||
        requestSequence.current !== requestId ||
        dateRef.current !== targetDate
      ) {
        return;
      }

      toast.success(
        requestType === "food" ? "Приём пищи добавлен" : "Активность добавлена",
      );
      form.reset(emptyValues(requestType, initialMealType));
      onClose();
    } catch (error) {
      if (!isMountedRef.current || requestSequence.current !== requestId) {
        return;
      }

      if (!(error instanceof ApiError)) {
        setSubmitError("Не удалось сохранить запись. Попробуйте снова.");
        return;
      }

      const fields = validationErrors(error.payload);
      let applied = false;

      for (const field of Object.keys(form.getValues()) as Array<
        keyof FormValues
      >) {
        if (fields[field]) {
          form.setError(field, { type: "server", message: fields[field] });
          applied = true;
        }
      }

      if (!applied) {
        setSubmitError(error.message);
      }
    }
  }

  function fieldError(name: keyof FormValues): string | undefined {
    return form.formState.errors[name]?.message;
  }

  return (
    <Modal
      title="Новая запись"
      onClose={handleClose}
      closeDisabled={isBusy}
    >
      <form
        className="modal-form"
        onSubmit={form.handleSubmit(onSubmit)}
        noValidate
      >
        <label>
          Тип записи
          <select
            disabled={isBusy}
            {...form.register("type")}
          >
            <option value="food">Питание</option>
            <option value="activity">Активность</option>
          </select>
        </label>

        {type === "food" ? (
          <label>
            Приём пищи
            <select
              disabled={isBusy}
              aria-invalid={Boolean(fieldError("meal_type"))}
              aria-describedby={
                fieldError("meal_type") ? "entry-meal-type-error" : undefined
              }
              {...form.register("meal_type")}
            >
              <option value="" disabled>
                Выберите приём пищи
              </option>
              {MEAL_TYPES.map((mealType) => (
                <option key={mealType} value={mealType}>
                  {MEAL_TYPE_LABELS[mealType]}
                </option>
              ))}
            </select>
          </label>
        ) : null}
        {fieldError("meal_type") ? (
          <p id="entry-meal-type-error" className="field-error">
            {fieldError("meal_type")}
          </p>
        ) : null}

        <label>
          Название
          <input
            type="text"
            maxLength={120}
            autoComplete="off"
            placeholder="Например, ужин или прогулка"
            data-autofocus
            disabled={isBusy}
            aria-invalid={Boolean(fieldError("name"))}
            aria-describedby={fieldError("name") ? "entry-name-error" : undefined}
            {...form.register("name")}
          />
        </label>
        {fieldError("name") ? (
          <p id="entry-name-error" className="field-error">
            {fieldError("name")}
          </p>
        ) : null}

        <label>
          Калории, ккал
          <input
            type="text"
            inputMode="decimal"
            autoComplete="off"
            placeholder="350"
            disabled={isBusy}
            aria-invalid={Boolean(fieldError("calories"))}
            aria-describedby={
              fieldError("calories") ? "entry-calories-error" : undefined
            }
            {...form.register("calories")}
          />
        </label>
        {fieldError("calories") ? (
          <p id="entry-calories-error" className="field-error">
            {fieldError("calories")}
          </p>
        ) : null}

        {type === "food" ? (
          <div className="field-grid triple">
            {(
              [
                ["protein", "Белки, г"],
                ["fat", "Жиры, г"],
                ["carbs", "Углеводы, г"],
              ] as const
            ).map(([field, label]) => (
              <div key={field}>
                <label>
                  {label}
                  <input
                    type="text"
                    inputMode="decimal"
                    autoComplete="off"
                    disabled={isBusy}
                    aria-invalid={Boolean(fieldError(field))}
                    aria-describedby={
                      fieldError(field) ? `entry-${field}-error` : undefined
                    }
                    {...form.register(field)}
                  />
                </label>
                {fieldError(field) ? (
                  <p id={`entry-${field}-error`} className="field-error">
                    {fieldError(field)}
                  </p>
                ) : null}
              </div>
            ))}
          </div>
        ) : (
          <div className="field-grid">
            {(
              [
                ["steps", "Шаги", "numeric"],
                ["minutes", "Минуты", "decimal"],
                ["distance_km", "Расстояние, км", "decimal"],
              ] as const
            ).map(([field, label, inputMode]) => (
              <div key={field}>
                <label>
                  {label}
                  <input
                    type={field === "steps" ? "number" : "text"}
                    min={field === "steps" ? "0" : undefined}
                    max={field === "steps" ? "1000000" : undefined}
                    step={field === "steps" ? "1" : undefined}
                    inputMode={inputMode}
                    autoComplete="off"
                    disabled={isBusy}
                    aria-invalid={Boolean(fieldError(field))}
                    aria-describedby={
                      fieldError(field) ? `entry-${field}-error` : undefined
                    }
                    {...form.register(field)}
                  />
                </label>
                {fieldError(field) ? (
                  <p id={`entry-${field}-error`} className="field-error">
                    {fieldError(field)}
                  </p>
                ) : null}
              </div>
            ))}
          </div>
        )}

        {submitError ? (
          <p className="error" role="alert">
            {submitError}
          </p>
        ) : null}

        <div className="modal-actions">
          <button type="submit" className="primary" disabled={isBusy}>
            {isBusy ? "Сохраняем…" : "Сохранить"}
          </button>
          <button
            type="button"
            className="secondary"
            onClick={handleClose}
            disabled={isBusy}
          >
            Отмена
          </button>
        </div>
      </form>
    </Modal>
  );
}
