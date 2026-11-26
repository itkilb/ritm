import { useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { z } from "zod";

import { SetupShell } from "@/components/layout/setup-shell";
import { useSession } from "@/hooks/use-session";
import { ApiError, validationErrors } from "@/lib/api";
import { calculateAge, detectTimeZone, formatNumber, parseIsoDate } from "@/lib/date";
import {
  ACTIVITY_OPTIONS,
  GOAL_OPTIONS,
  previewNutritionGoal,
  saveMyProfile,
  type ActivityLevel,
  type Gender,
  type NutritionGoalType,
  type NutritionPreview,
  type ProfileInput,
} from "@/lib/goal-api";
import { cn } from "@/lib/utils";

const numericField = (min: number, max: number, label: string) =>
  z
    .string()
    .trim()
    .min(1, `Укажите ${label.toLowerCase()}.`)
    .refine((value) => Number.isFinite(Number(value)), "Введите число.")
    .refine(
      (value) => Number(value) >= min && Number(value) <= max,
      `Допустимое значение — от ${min} до ${max}.`,
    );

const optionalFatField = z
  .string()
  .trim()
  .refine(
    (value) =>
      value === "" ||
      (Number.isFinite(Number(value)) &&
        Number(value) >= 5 &&
        Number(value) <= 60),
    "Укажите процент жира от 5 до 60 или оставьте поле пустым.",
  );

const profileSchema = z.object({
  weight_kg: numericField(35, 300, "вес"),
  height_cm: numericField(120, 230, "рост"),
  birth_date: z
    .string()
    .trim()
    .min(1, "Укажите дату рождения.")
    .refine(
      (value) => parseIsoDate(value) !== null,
      "Дата рождения должна быть действительной датой.",
    ),
  fat_percentage: optionalFatField,
  gender: z
    .string()
    .min(1, "Выберите пол для расчёта.")
    .refine(
      (value): boolean => value === "male" || value === "female",
      "Выберите корректный пол для расчёта.",
    ),
  activity_level: z
    .string()
    .min(1, "Выберите уровень активности.")
    .refine(
      (value) => ACTIVITY_OPTIONS.some((option) => option.value === value),
      "Выберите корректный уровень активности.",
    ),
  goal: z
    .string()
    .min(1, "Выберите цель.")
    .refine(
      (value): boolean => value === "lose_weight" || value === "gain_muscle",
      "Выберите одну из двух целей.",
    ),
  timezone: z.string().min(1, "Не удалось определить часовой пояс."),
});

const onboardingSchema = profileSchema.superRefine((values, context) => {
  const age = calculateAge(values.birth_date, values.timezone);

  if (age === null) {
    return;
  }

  if (age < 18 || age > 100) {
    context.addIssue({
      code: "custom",
      path: ["birth_date"],
      message: "Возраст для расчёта должен быть от 18 до 100 лет.",
    });
  }
});

type FormValues = z.infer<typeof onboardingSchema>;

function profileToForm(profile: ProfileInput | null | undefined): FormValues {
  return {
    weight_kg: profile ? String(profile.weight_kg) : "",
    height_cm: profile ? String(profile.height_cm) : "",
    birth_date: profile?.birth_date ?? "",
    fat_percentage:
      profile?.fat_percentage === null || profile?.fat_percentage === undefined
        ? ""
        : String(profile.fat_percentage),
    gender: profile?.gender ?? "",
    activity_level: profile?.activity_level ?? "sedentary",
    goal: profile?.goal ?? "lose_weight",
    // The browser supplies the IANA zone; the server validates and stores it.
    timezone: detectTimeZone(),
  };
}

function toProfileInput(values: FormValues): ProfileInput {
  return {
    weight_kg: Number(values.weight_kg.replace(",", ".")),
    height_cm: Number(values.height_cm.replace(",", ".")),
    birth_date: values.birth_date,
    fat_percentage:
      values.fat_percentage.trim() === ""
        ? null
        : Number(values.fat_percentage.replace(",", ".")),
    gender: values.gender as Gender,
    activity_level: values.activity_level as ActivityLevel,
    goal: values.goal as NutritionGoalType,
    timezone: values.timezone,
  };
}

function goalSubtitle(goal: NutritionGoalType): string {
  return goal === "lose_weight"
    ? "Похудение · умеренный дефицит 15% от расчётной поддержки."
    : "Набор массы · профицит 10% от расчётной поддержки.";
}

function calculationParagraph(preview: NutritionPreview): string {
  const { explanation } = preview;
  const goalAdjustment =
    preview.goal === "lose_weight"
      ? "дефицит 15%"
      : "профицит 10%";
  const proteinBasis =
    explanation.lean_mass_kg !== null
      ? `2 г на кг безжировой массы (${formatNumber(
          explanation.lean_mass_kg,
        )} кг), рассчитанной из указанного процента жира`
      : "1,6 г на кг веса";

  return [
    `Возраст: ${explanation.age}.`,
    `Основной обмен: ${formatNumber(explanation.bmr)} ккал.`,
    `Поддержание: ${formatNumber(
      explanation.tdee,
    )} ккал с коэффициентом активности ${formatNumber(
      explanation.activity_coefficient,
    )} (${explanation.activity_label.toLowerCase()}).`,
    `Поправка на цель: ×${formatNumber(explanation.goal_multiplier)} (${goalAdjustment}).`,
    `Белки: ${proteinBasis}.`,
    "Жиры: около 25% калорий. Углеводы: оставшиеся калории. БЖУ округлены до граммов.",
  ].join(" ");
}

function FieldError({ id, message }: { id: string; message?: string }) {
  if (!message) {
    return null;
  }

  return (
    <p id={id} className="field-error">
      {message}
    </p>
  );
}

export function OnboardingPage() {
  const session = useSession();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [preview, setPreview] = useState<NutritionPreview | null>(null);
  const [previewInput, setPreviewInput] = useState<ProfileInput | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);

  const isEditing = session.data?.profile_completed === true;

  const form = useForm<FormValues>({
    resolver: zodResolver(onboardingSchema),
    defaultValues: profileToForm(session.data?.profile),
    mode: "onTouched",
  });

  const previewMutation = useMutation({ mutationFn: previewNutritionGoal });
  const saveMutation = useMutation({ mutationFn: saveMyProfile });

  const selectedGoal = form.watch("goal");
  const isBusy =
    previewMutation.isPending ||
    saveMutation.isPending ||
    form.formState.isSubmitting;

  function applyServerFieldErrors(error: ApiError): boolean {
    const fields = validationErrors(error.payload);
    let applied = false;

    for (const [field, message] of Object.entries(fields)) {
      if (field in profileSchema.shape) {
        form.setError(field as keyof FormValues, {
          type: "server",
          message,
        });
        applied = true;
      }
    }

    return applied;
  }

  async function onPreview(values: FormValues) {
    form.clearErrors();
    setSaveError(null);

    try {
      const input = toProfileInput(values);
      const result = await previewMutation.mutateAsync(input);
      setPreview(result);
      setPreviewInput(input);
    } catch (error) {
      if (!(error instanceof ApiError)) {
        form.setError("root", {
          message: "Не удалось рассчитать КБЖУ. Попробуйте снова.",
        });
        return;
      }

      const hasFieldErrors = applyServerFieldErrors(error);
      const fields = validationErrors(error.payload);

      if (!hasFieldErrors || fields.calculation) {
        form.setError("root", { message: fields.calculation ?? error.message });
      }
    }
  }

  async function onConfirm() {
    if (!previewInput || saveMutation.isPending) {
      return;
    }

    setSaveError(null);

    try {
      const saved = await saveMutation.mutateAsync(previewInput);

      queryClient.setQueryData(["session"], saved.user);
      queryClient.setQueryData(["goals", "current"], saved.goal);
      // Current/future targets changed; historical goal snapshots stay untouched.
      void queryClient.invalidateQueries({ queryKey: ["goals"] });
      void queryClient.invalidateQueries({ queryKey: ["dashboard"] });
      void queryClient.invalidateQueries({ queryKey: ["weekly"] });
      void queryClient.invalidateQueries({ queryKey: ["progress"] });
      void queryClient.invalidateQueries({ queryKey: ["weight"] });
      toast.success("Цель сохранена");
      navigate("/dashboard", { replace: true });
    } catch (error) {
      if (!(error instanceof ApiError)) {
        setSaveError("Не удалось сохранить цель. Попробуйте снова.");
        return;
      }

      const fields = validationErrors(error.payload);
      const fieldKeys = Object.keys(fields).filter(
        (field) => field in profileSchema.shape,
      );

      if (fieldKeys.length > 0) {
        setPreview(null);
        for (const field of fieldKeys) {
          form.setError(field as keyof FormValues, {
            type: "server",
            message: fields[field],
          });
        }
        form.setError("root", {
          message: "Сервер не принял параметры. Проверьте поля и повторите.",
        });
        return;
      }

      setSaveError(error.message);
    }
  }

  if (preview) {
    return (
      <SetupShell step={3}>
        <div className="setup-form">
          <span className="eyebrow">ТВОЙ ПЛАН ГОТОВ</span>
          <h1>
            Баланс, с которого
            <br />
            можно начать
          </h1>
          <p className="setup-subtitle">{goalSubtitle(preview.goal)}</p>

          {saveError ? (
            <div className="inline-alert" role="alert">
              <span>
                <b>Не удалось сохранить цель.</b> {saveError}
              </span>
              <button
                type="button"
                className="text-btn"
                onClick={() => void onConfirm()}
                disabled={isBusy}
              >
                {isBusy ? "Сохраняем…" : "Повторить сохранение"}
              </button>
            </div>
          ) : null}

          <div className="result-calories">
            <span>Твой ориентир на день</span>
            <strong>{formatNumber(preview.calories)}</strong>
            <span>ккал</span>
          </div>

          <div className="result-macros">
            <div>
              <i className="protein-dot" aria-hidden="true" />
              <b>{formatNumber(preview.protein)} г</b>
              <span>Белки</span>
            </div>
            <div>
              <i className="fat-dot" aria-hidden="true" />
              <b>{formatNumber(preview.fat)} г</b>
              <span>Жиры</span>
            </div>
            <div>
              <i className="carb-dot" aria-hidden="true" />
              <b>{formatNumber(preview.carbs)} г</b>
              <span>Углеводы</span>
            </div>
          </div>

          <details className="calculation">
            <summary>Как получился этот расчёт</summary>
            <p>{calculationParagraph(preview)}</p>
            <p>
              Оценка основного обмена —{" "}
              <a
                href="https://pubmed.ncbi.nlm.nih.gov/2305711/"
                target="_blank"
                rel="noopener noreferrer"
              >
                формула Миффлина — Сан Жеора
              </a>
              . Коэффициенты активности и распределение БЖУ — стандартные
              ориентиры расчёта.
            </p>
          </details>

          <div className="result-actions">
            <button
              type="button"
              className="primary wide"
              onClick={() => void onConfirm()}
              disabled={isBusy}
            >
              {saveMutation.isPending ? "Сохраняем…" : "Перейти к моему дню"}
            </button>
            <button
              type="button"
              className="text-btn back-button"
              onClick={() => {
                setSaveError(null);
                setPreview(null);
              }}
              disabled={isBusy}
            >
              Изменить параметры
            </button>
          </div>
        </div>
      </SetupShell>
    );
  }

  const rootError = form.formState.errors.root?.message;

  return (
    <SetupShell step={2}>
      <form
        className="setup-form"
        noValidate
        onSubmit={form.handleSubmit(onPreview)}
      >
        <span className="eyebrow">НЕМНОГО О ТЕБЕ</span>
        <h1>Настроим твой баланс</h1>
        <p className="setup-subtitle">
          Параметры помогут рассчитать стартовые КБЖУ. Их всегда можно изменить.
        </p>

        <div className="field-grid">
          <div>
            <label htmlFor="onboarding-weight">Вес, кг</label>
            <input
              id="onboarding-weight"
              type="number"
              min="35"
              max="300"
              step="0.1"
              inputMode="decimal"
              placeholder="70"
              autoComplete="off"
              disabled={isBusy}
              aria-invalid={Boolean(form.formState.errors.weight_kg)}
              aria-describedby={
                form.formState.errors.weight_kg
                  ? "onboarding-weight-error"
                  : undefined
              }
              {...form.register("weight_kg")}
            />
            <FieldError
              id="onboarding-weight-error"
              message={form.formState.errors.weight_kg?.message}
            />
          </div>

          <div>
            <label htmlFor="onboarding-height">Рост, см</label>
            <input
              id="onboarding-height"
              type="number"
              min="120"
              max="230"
              step="0.1"
              inputMode="decimal"
              placeholder="175"
              autoComplete="off"
              disabled={isBusy}
              aria-invalid={Boolean(form.formState.errors.height_cm)}
              aria-describedby={
                form.formState.errors.height_cm
                  ? "onboarding-height-error"
                  : undefined
              }
              {...form.register("height_cm")}
            />
            <FieldError
              id="onboarding-height-error"
              message={form.formState.errors.height_cm?.message}
            />
          </div>

          <div>
            <label htmlFor="onboarding-birth">Дата рождения</label>
            <input
              id="onboarding-birth"
              type="date"
              autoComplete="bday"
              disabled={isBusy}
              aria-invalid={Boolean(form.formState.errors.birth_date)}
              aria-describedby={
                form.formState.errors.birth_date
                  ? "onboarding-birth-error"
                  : undefined
              }
              {...form.register("birth_date")}
            />
            <FieldError
              id="onboarding-birth-error"
              message={form.formState.errors.birth_date?.message}
            />
          </div>

          <div>
            <label htmlFor="onboarding-fat">
              Процент жира, %{" "}
              <span className="optional">необязательно</span>
            </label>
            <input
              id="onboarding-fat"
              type="number"
              min="5"
              max="60"
              step="0.1"
              inputMode="decimal"
              placeholder="Если знаешь"
              autoComplete="off"
              disabled={isBusy}
              aria-invalid={Boolean(form.formState.errors.fat_percentage)}
              aria-describedby={
                form.formState.errors.fat_percentage
                  ? "onboarding-fat-error"
                  : undefined
              }
              {...form.register("fat_percentage")}
            />
            <FieldError
              id="onboarding-fat-error"
              message={form.formState.errors.fat_percentage?.message}
            />
          </div>
        </div>

        <div className="field-grid">
          <div>
            <label htmlFor="onboarding-gender">Пол для расчёта</label>
            <select
              id="onboarding-gender"
              disabled={isBusy}
              aria-invalid={Boolean(form.formState.errors.gender)}
              aria-describedby={
                form.formState.errors.gender
                  ? "onboarding-gender-error"
                  : undefined
              }
              {...form.register("gender")}
            >
              <option value="" disabled>
                Выбрать
              </option>
              <option value="male">Мужской</option>
              <option value="female">Женский</option>
            </select>
            <FieldError
              id="onboarding-gender-error"
              message={form.formState.errors.gender?.message}
            />
          </div>

          <div>
            <label htmlFor="onboarding-activity">Уровень активности</label>
            <select
              id="onboarding-activity"
              disabled={isBusy}
              aria-invalid={Boolean(form.formState.errors.activity_level)}
              aria-describedby={
                form.formState.errors.activity_level
                  ? "onboarding-activity-error"
                  : undefined
              }
              {...form.register("activity_level")}
            >
              {ACTIVITY_OPTIONS.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <FieldError
              id="onboarding-activity-error"
              message={form.formState.errors.activity_level?.message}
            />
          </div>
        </div>

        <fieldset
          className="goal-field"
          aria-describedby={
            form.formState.errors.goal ? "onboarding-goal-error" : undefined
          }
        >
          <legend>Какая у тебя цель?</legend>
          <div className="goal-options">
            {GOAL_OPTIONS.map((option) => (
              <label key={option.value}>
                <input
                  type="radio"
                  value={option.value}
                  disabled={isBusy}
                  {...form.register("goal")}
                />
                <span
                  className={cn(
                    selectedGoal === option.value && "is-selected",
                  )}
                >
                  <b>{option.label}</b>
                  <small>{option.description}</small>
                </span>
              </label>
            ))}
          </div>
        </fieldset>
        <FieldError
          id="onboarding-goal-error"
          message={form.formState.errors.goal?.message}
        />

        {rootError ? (
          <p className="error" role="alert">
            {rootError}
          </p>
        ) : null}

        <button className="primary wide" type="submit" disabled={isBusy}>
          {previewMutation.isPending ? "Рассчитываем…" : "Рассчитать мои КБЖУ"}
        </button>

        {isEditing ? (
          <button
            type="button"
            className="text-btn back-button"
            onClick={() => navigate("/dashboard")}
            disabled={isBusy}
          >
            Отмена
          </button>
        ) : null}

        <p className="prototype-note">
          Расчёт предназначен для взрослых от 18 лет. Это стартовая оценка, а не
          индивидуальное назначение.
        </p>
      </form>
    </SetupShell>
  );
}
