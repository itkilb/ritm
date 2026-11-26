import { zodResolver } from "@hookform/resolvers/zod";
import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";
import {
  useFieldArray,
  useForm,
  type FieldErrors,
  type FieldPath,
  type UseFormReturn,
} from "react-hook-form";
import { toast } from "sonner";

import { ExercisePicker } from "@/components/workouts/exercise-picker";
import { Modal } from "@/components/ui/modal";
import {
  useCreateWorkout,
  useUpdateWorkout,
} from "@/hooks/use-workouts";
import { useWorkoutPreview } from "@/hooks/use-workout-preview";
import { ApiError, validationErrors } from "@/lib/api";
import { decimalParts } from "@/lib/entry-form";
import { formatNumber, formatWeight } from "@/lib/date";
import {
  ACTIVITY_KIND_OPTIONS,
  caloriePreviewSchema,
  emptyExercise,
  emptySet,
  emptyWorkoutValues,
  EXERCISE_MODE_LABELS,
  FEELING_LABELS,
  formToCaloriePreviewInput,
  formToWorkoutInput,
  RUN_SURFACE_LABELS,
  STRENGTH_CHARACTER_OPTIONS,
  switchWorkoutType,
  WEIGHT_SOURCE_LABELS,
  workoutFormSchema,
  workoutServerErrorEntries,
  workoutToFormValues,
  WORKOUT_TYPE_LABELS,
  type WorkoutExerciseForm,
  type WorkoutFormValues,
  type WorkoutSetForm,
} from "@/lib/workout-form";
import type {
  ExerciseMode,
  Workout,
  WorkoutCaloriePreview,
  WorkoutType,
} from "@/lib/workout-api";
import type { SessionUser } from "@/hooks/use-session";
import { distanceFromSpeed, runningMetrics } from "@/lib/workout-math";

export type WorkoutEditorState = {
  mode: "create" | "edit" | "repeat";
  type: WorkoutType;
  workout?: Workout;
  nonce: number;
};

function fieldError(
  errors: FieldErrors<WorkoutFormValues>,
  path: string,
): string | undefined {
  const segments = path.split(".");
  let current: unknown = errors;

  for (const segment of segments) {
    if (typeof current !== "object" || current === null) {
      return undefined;
    }

    current = (current as Record<string, unknown>)[segment];
  }

  if (
    typeof current === "object" &&
    current !== null &&
    "message" in current &&
    typeof (current as { message?: unknown }).message === "string"
  ) {
    return (current as { message: string }).message;
  }

  return undefined;
}

function messageAt(errors: FieldErrors<WorkoutFormValues>, path: string): string | undefined {
  return fieldError(errors, path);
}

function setNestedError(
  form: UseFormReturn<WorkoutFormValues>,
  path: string,
  message: string,
): void {
  form.setError(path as FieldPath<WorkoutFormValues>, {
    type: "server",
    message,
  });
}

function WorkoutSetRow({
  form,
  exerciseIndex,
  setIndex,
  mode,
  onRemove,
  canRemove,
  busy,
}: {
  form: UseFormReturn<WorkoutFormValues>;
  exerciseIndex: number;
  setIndex: number;
  mode: ExerciseMode;
  onRemove: () => void;
  canRemove: boolean;
  busy: boolean;
}) {
  const base = `exercises.${exerciseIndex}.sets.${setIndex}`;
  return (
    <div className={mode === "time" || mode === "reps" ? "set-row time-mode" : "set-row"}>
      <span className="set-number">{setIndex + 1}</span>
      {(mode === "weighted" || mode === "bodyweight") && (
        <label className="set-field">
          <span className="sr-only">
            {mode === "bodyweight" ? "Дополнительный вес, кг" : "Вес, кг"}
          </span>
          <input
            type="text"
            inputMode="decimal"
            autoComplete="off"
            placeholder="Вес"
            disabled={busy}
            aria-invalid={Boolean(
              messageAt(form.formState.errors, `${base}.weight_kg`),
            )}
            {...form.register(`${base}.weight_kg` as FieldPath<WorkoutFormValues>)}
          />
        </label>
      )}
      {mode !== "time" && (
        <label className="set-field">
          <span className="sr-only">Повторения</span>
          <input
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder="Повторения"
            disabled={busy}
            aria-invalid={Boolean(messageAt(form.formState.errors, `${base}.reps`))}
            {...form.register(`${base}.reps` as FieldPath<WorkoutFormValues>)}
          />
        </label>
      )}
      {mode === "time" && (
        <label className="set-field">
          <span className="sr-only">Время, сек</span>
          <input
            type="text"
            inputMode="numeric"
            autoComplete="off"
            placeholder="Секунды"
            disabled={busy}
            aria-invalid={Boolean(
              messageAt(form.formState.errors, `${base}.seconds`),
            )}
            {...form.register(`${base}.seconds` as FieldPath<WorkoutFormValues>)}
          />
        </label>
      )}
      <button
        type="button"
        className="icon-button"
        aria-label={`Удалить подход ${setIndex + 1}`}
        onClick={onRemove}
        disabled={busy || !canRemove}
      >
        ×
      </button>
    </div>
  );
}

function ExerciseEditor({
  form,
  index,
  busy,
  onRemove,
}: {
  form: UseFormReturn<WorkoutFormValues>;
  index: number;
  busy: boolean;
  onRemove: () => void;
}) {
  const { fields, append, remove } = useFieldArray({
    control: form.control,
    name: `exercises.${index}.sets` as const,
  });
  const mode = form.watch(`exercises.${index}.mode`);
  const exerciseName = form.watch(`exercises.${index}.name`);
  const base = `exercises.${index}`;

  function changeMode(nextMode: ExerciseMode) {
    const current = form.getValues(`exercises.${index}.sets`);
    const converted: WorkoutSetForm[] = current.map((set) => {
      if (nextMode === "time") {
        return {
          weight_kg: "",
          reps: "",
          seconds: set.seconds || "30",
        };
      }

      if (nextMode === "reps") {
        return {
          weight_kg: "",
          reps: set.reps || "10",
          seconds: "",
        };
      }

      return {
        weight_kg: set.weight_kg || "0",
        reps: set.reps || "10",
        seconds: "",
      };
    });

    form.setValue(`exercises.${index}.mode`, nextMode, {
      shouldDirty: true,
      shouldValidate: false,
    });
    form.setValue(
      `exercises.${index}.sets`,
      converted.length > 0 ? converted : [emptySet(nextMode)],
      { shouldDirty: true, shouldValidate: false },
    );
  }

  const setsError = messageAt(form.formState.errors, `${base}.sets`);
  const exerciseRootError = messageAt(form.formState.errors, base);

  return (
    <section className="exercise-card" aria-label={`Упражнение ${index + 1}`}>
      <div className="exercise-head">
        <ExercisePicker form={form} index={index} busy={busy} />
        <button
          type="button"
          className="icon-button exercise-remove"
          aria-label={`Удалить упражнение ${exerciseName || index + 1}`}
          onClick={onRemove}
          disabled={busy}
        >
          ×
        </button>
      </div>
      {exerciseRootError ? (
        <p className="field-error">{exerciseRootError}</p>
      ) : null}

      <div className="exercise-mode">
        <span className="last-result">
          {exerciseName
            ? `Упражнение: ${exerciseName}`
            : "Назови упражнение и добавь подходы"}
        </span>
        <label>
          <span className="sr-only">Способ учёта упражнения</span>
          <select
            className="exercise-measure"
            aria-label={`Способ учёта упражнения ${index + 1}`}
            value={mode}
            disabled={busy}
            onChange={(event) => changeMode(event.target.value as ExerciseMode)}
          >
            {(Object.keys(EXERCISE_MODE_LABELS) as ExerciseMode[]).map((value) => (
              <option key={value} value={value}>
                {EXERCISE_MODE_LABELS[value]}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div
        className={
          mode === "time" || mode === "reps" ? "set-head time-mode" : "set-head"
        }
        aria-hidden="true"
      >
        <span>Подход</span>
        {mode === "time" ? (
          <span>Время, сек</span>
        ) : mode === "reps" ? (
          <span>Повторения</span>
        ) : (
          <>
            <span>{mode === "bodyweight" ? "Доп. вес, кг" : "Вес, кг"}</span>
            <span>Повторения</span>
          </>
        )}
        <span />
      </div>

      <div className="set-list">
        {fields.map((field, setIndex) => (
          <WorkoutSetRow
            key={field.id}
            form={form}
            exerciseIndex={index}
            setIndex={setIndex}
            mode={mode}
            busy={busy}
            canRemove={fields.length > 1}
            onRemove={() => remove(setIndex)}
          />
        ))}
      </div>

      {setsError ? <p className="field-error">{setsError}</p> : null}
      {fields.map((field, currentSetIndex) => {
        const setRoot = messageAt(
          form.formState.errors,
          `${base}.sets.${currentSetIndex}`,
        );

        return (
          <div key={`${field.id}-errors`}>
            {setRoot ? (
              <p className="field-error">
                Подход {currentSetIndex + 1}: {setRoot}
              </p>
            ) : null}
            {(["weight_kg", "reps", "seconds"] as const).map((fieldName) => {
              const message = messageAt(
                form.formState.errors,
                `${base}.sets.${currentSetIndex}.${fieldName}`,
              );

              return message ? (
                <p className="field-error" key={`${field.id}-${fieldName}`}>
                  Подход {currentSetIndex + 1}: {message}
                </p>
              ) : null;
            })}
          </div>
        );
      })}

      <button
        type="button"
        className="add-set"
        onClick={() => append(emptySet(mode))}
        disabled={busy}
      >
        ＋ Подход
      </button>
    </section>
  );
}

function durationLabel(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined || seconds <= 0) {
    return "—";
  }

  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;

  return rest === 0 ? `${minutes} мин` : `${minutes} мин ${rest} с`;
}

function CaloriePreviewPanel({
  preview,
  loading,
  error,
  onRetry,
  onManual,
  onIgnoreIncline,
  canIgnoreIncline,
}: {
  preview: WorkoutCaloriePreview | null;
  loading: boolean;
  error: Error | null;
  onRetry: () => void;
  onManual: () => void;
  onIgnoreIncline: () => void;
  canIgnoreIncline: boolean;
}) {
  if (loading) {
    return (
      <p className="calorie-preview-status" aria-live="polite">
        Рассчитываем калории…
      </p>
    );
  }

  if (error) {
    return (
      <div className="calorie-preview-error" role="alert">
        <p>Не удалось получить расчёт: {error.message}</p>
        <button type="button" className="text-btn" onClick={onRetry}>
          Повторить
        </button>
      </div>
    );
  }

  if (!preview) {
    return (
      <p className="calorie-preview-status">
        Заполни обязательные поля — сервер рассчитает калории и покажет, как это
        получилось.
      </p>
    );
  }

  const calculation = preview.calculation;
  const explanation = preview.explanation;
  const calculated =
    calculation.calories_status === "calculated" &&
    calculation.calories !== null;

  return (
    <div
      className="calorie-preview"
      aria-label="Предпросмотр расчёта калорий"
      aria-live="polite"
    >
      {calculated ? (
        <>
          <div className="calorie-preview-main">
            <strong>≈ {formatNumber(calculation.calories ?? 0)} ккал активности</strong>
            <span>
              ≈ {formatNumber(calculation.gross_kcal ?? 0)} ккал всего
            </span>
          </div>
          <p className="calorie-preview-meta">
            MET {formatNumber(calculation.met_value ?? 0)} ·{" "}
            {explanation.category ?? "категория не указана"} · вес{" "}
            {formatWeight(calculation.weight_kg ?? 0)} кг
            {calculation.weight_source
              ? ` (${WEIGHT_SOURCE_LABELS[calculation.weight_source]})`
              : ""}{" "}
            · {durationLabel(explanation.duration_seconds)}
          </p>
        </>
      ) : (
        <div className="calorie-preview-main missing">
          <strong>Калории не рассчитаны</strong>
          <span>{explanation.reason ?? "Недостаточно данных для автоматического расчёта."}</span>
        </div>
      )}

      {calculation.calorie_approximate ? (
        <p className="calorie-preview-warning">
          Выбрана ближайшая категория справочника — значение приблизительное.
        </p>
      ) : null}

      {explanation.warnings.map((warning) => (
        <p className="calorie-preview-warning" key={warning}>
          {warning}
        </p>
      ))}

      {explanation.status === "unsupported" && canIgnoreIncline ? (
        <div className="calorie-preview-actions">
          <button type="button" className="secondary" onClick={onIgnoreIncline}>
            Оценить без учёта уклона
          </button>
          <button type="button" className="text-btn" onClick={onManual}>
            Указать вручную
          </button>
        </div>
      ) : null}

      {explanation.status !== "calculated" && !canIgnoreIncline ? (
        <div className="calorie-preview-actions">
          <button type="button" className="text-btn" onClick={onManual}>
            Указать калории вручную
          </button>
        </div>
      ) : null}

      <details className="calorie-explanation">
        <summary>Как рассчитано</summary>
        <p>
          {calculated
            ? "Активность = round((MET − 1) × вес × время). Общий расход = round(MET × вес × время)."
            : "Автоматический расчёт не был выполнен, поэтому калории не подставляются нулём."}
        </p>
        {calculated ? (
          <ul>
            <li>
              Категория: {explanation.category ?? "—"}
              {explanation.met_code ? ` (код ${explanation.met_code})` : ""}
            </li>
            <li>
              MET: {formatNumber(explanation.met ?? 0)}; вес:{" "}
              {formatWeight(explanation.weight_kg ?? 0)} кг
              {explanation.weight_source
                ? `, ${WEIGHT_SOURCE_LABELS[explanation.weight_source]}`
                : ""}
            </li>
            <li>Длительность: {durationLabel(explanation.duration_seconds)}</li>
            <li>
              Расход сверх условного покоя: {formatNumber(explanation.active_kcal ?? 0)} ккал;
              общий: {formatNumber(explanation.gross_kcal ?? 0)} ккал.
            </li>
          </ul>
        ) : null}
        {explanation.notes.map((note) => (
          <p className="calorie-preview-note" key={note}>
            {note}
          </p>
        ))}
        <p className="calorie-preview-note">
          {explanation.catalog}
          {explanation.catalog_version ? `, ${explanation.catalog_version}` : ""}.
          {explanation.catalog_url ? (
            <>
              {" "}
              <a
                href={explanation.catalog_url}
                target="_blank"
                rel="noreferrer"
              >
                Справочник MET
              </a>
            </>
          ) : null}
        </p>
      </details>
    </div>
  );
}

export function WorkoutEditor({
  state,
  selectedDate,
  sessionId,
  onClose,
  onSaved,
}: {
  state: WorkoutEditorState;
  selectedDate: string;
  sessionId: SessionUser["id"] | null;
  onClose: () => void;
  onSaved: (workout: Workout) => void;
}) {
  const queryClient = useQueryClient();
  const createMutation = useCreateWorkout();
  const updateMutation = useUpdateWorkout();
  const [submitError, setSubmitError] = useState<string | null>(null);
  const forceRecalculate = useRef(false);
  // Mirrored in state so the explicit recalculate flag reaches the next preview request.
  const [recalculateRequested, setRecalculateRequested] = useState(false);
  const mountedRef = useRef(true);
  const requestSequence = useRef(0);
  const pageDateRef = useRef(selectedDate);
  const sessionIdAtMount = useRef<SessionUser["id"] | null>(sessionId);

  // A response from a previous account must not touch this editor.
  function isSameSession(): boolean {
    const expected = sessionIdAtMount.current;

    if (expected === null) {
      return false;
    }

    const current = queryClient.getQueryData<SessionUser | null>(["session"]);

    return (
      current !== null &&
      current !== undefined &&
      String(current.id) === String(expected)
    );
  }

  const initialValues =
    state.mode === "edit" && state.workout
      ? workoutToFormValues(state.workout)
      : state.mode === "repeat" && state.workout
        ? workoutToFormValues(state.workout, selectedDate)
        : emptyWorkoutValues(state.type, selectedDate);

  const form = useForm<WorkoutFormValues>({
    resolver: zodResolver(workoutFormSchema),
    defaultValues: initialValues,
    mode: "onTouched",
  });

  const {
    fields: exerciseFields,
    append: appendExercise,
    remove: removeExercise,
  } = useFieldArray({
    control: form.control,
    name: "exercises",
  });

  const busy =
    createMutation.isPending || updateMutation.isPending || form.formState.isSubmitting;
  const watched = form.watch();
  const type = watched.type;
  const inputMode = watched.input_mode;
  const durationMinutes = watched.duration_minutes;
  const extraSeconds = watched.extra_seconds;
  const distanceText = watched.distance_km;
  const speedText = watched.speed_kmh;
  const inclineText = watched.incline_percent;
  const activityKind = watched.activity_kind;
  const isStrength = type === "strength";
  const isAuto = watched.calories_mode === "auto";

  // Name/exercises are not calorie inputs; an unfinished strength draft can still preview.
  const previewValid = caloriePreviewSchema.safeParse(watched).success;
  const previewInput = previewValid
    ? formToCaloriePreviewInput(watched, {
        recalculate: recalculateRequested,
      })
    : null;
  const preview = useWorkoutPreview({
    input: isAuto ? previewInput : null,
    workoutId: state.mode === "edit" ? state.workout?.id : null,
    enabled: isAuto && previewValid,
    sessionId,
  });

  useEffect(() => {
    mountedRef.current = true;

    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    pageDateRef.current = selectedDate;
  }, [selectedDate]);

  useEffect(() => {
    if (type === "cardio" && activityKind === "outdoor_bike" && (distanceText.trim() !== "" || speedText.trim() !== "")) {
      if (watched.cardio_intensity !== "") {
        form.setValue("cardio_intensity", "", { shouldValidate: false });
      }
    }

    if (type === "cardio" && activityKind === "stationary_bike" && watched.power_watts.trim() !== "" && watched.cardio_intensity === "general") {
      form.setValue("cardio_intensity", "", { shouldValidate: false });
    }

    if (watched.incline_ignored && (type !== "run" || inclineText.trim() === "" || Number(inclineText) <= 0)) {
      form.setValue("incline_ignored", false, { shouldValidate: false });
    }
  }, [
    type,
    activityKind,
    distanceText,
    speedText,
    inclineText,
    watched.cardio_intensity,
    watched.power_watts,
    watched.incline_ignored,
    form,
  ]);

  const totalSeconds =
    Number(durationMinutes) * 60 + Number(extraSeconds || 0);
  const enteredDistance = decimalParts(distanceText)?.value;
  const enteredSpeed = decimalParts(speedText)?.value;
  const distance =
    inputMode === "speed"
      ? enteredSpeed !== undefined
        ? distanceFromSpeed(enteredSpeed, totalSeconds)
        : null
      : (enteredDistance ?? null);
  const metrics =
    distance !== null && distance > 0 && totalSeconds > 0
      ? runningMetrics(distance, totalSeconds)
      : null;
  const calculatedDistanceExceedsLimit =
    inputMode === "speed" && distance !== null && distance > 1000;

  function handleClose() {
    if (busy) {
      return;
    }

    onClose();
  }

  function changeType(nextType: WorkoutType) {
    if (nextType === type) {
      return;
    }

    const next = switchWorkoutType(form.getValues(), nextType);
    form.reset(next);
    setSubmitError(null);
  }

  function changeMode(mode: WorkoutFormValues["calories_mode"]) {
    form.setValue("calories_mode", mode, {
      shouldDirty: true,
      shouldValidate: true,
    });
  }

  function requestRecalculation() {
    forceRecalculate.current = true;
    setRecalculateRequested(true);
    preview.reload();
  }

  function handleRecalculate() {
    requestRecalculation();
  }

  function ignoreIncline() {
    form.setValue("incline_ignored", true, {
      shouldDirty: true,
      shouldValidate: true,
    });
    requestRecalculation();
  }

  async function onSubmit(values: WorkoutFormValues) {
    if (busy) {
      return;
    }

    form.clearErrors();
    setSubmitError(null);

    const requestId = ++requestSequence.current;
    const requestPageDate = pageDateRef.current;
    const payload = formToWorkoutInput(values, {
      recalculate: forceRecalculate.current,
    });

    try {
      const saved =
        state.mode === "edit" && state.workout
          ? await updateMutation.mutateAsync({
              id: state.workout.id,
              input: payload,
              previousDate: state.workout.date,
            })
          : await createMutation.mutateAsync(payload);

      if (
        !mountedRef.current ||
        requestSequence.current !== requestId ||
        pageDateRef.current !== requestPageDate ||
        !isSameSession()
      ) {
        return;
      }

      forceRecalculate.current = false;
      setRecalculateRequested(false);
      toast.success(
        state.mode === "edit" ? "Тренировка обновлена" : "Тренировка сохранена",
      );
      onSaved(saved);
    } catch (error) {
      if (
        !mountedRef.current ||
        requestSequence.current !== requestId ||
        !isSameSession()
      ) {
        return;
      }

      if (!(error instanceof ApiError)) {
        setSubmitError("Не удалось сохранить тренировку. Попробуйте снова.");
        return;
      }

      const fields = validationErrors(error.payload);
      const entries = workoutServerErrorEntries(fields);
      const unhandled: string[] = [];

      for (const { path, message } of entries) {
        const mapped =
          path === "duration_seconds"
            ? "duration_minutes"
            : path === "exercises"
              ? "exercises"
              : path;

        if (
          mapped === "type" ||
          mapped === "name" ||
          mapped === "date" ||
          mapped === "duration_minutes" ||
          mapped === "extra_seconds" ||
          mapped === "calories_mode" ||
          mapped === "calories" ||
          mapped === "calorie_weight_kg" ||
          mapped === "strength_character" ||
          mapped === "activity_kind" ||
          mapped === "cardio_intensity" ||
          mapped === "power_watts" ||
          mapped === "incline_ignored" ||
          mapped === "run_surface" ||
          mapped === "feeling" ||
          mapped === "note" ||
          mapped === "input_mode" ||
          mapped === "distance_km" ||
          mapped === "speed_kmh" ||
          mapped === "pulse" ||
          mapped === "incline_percent" ||
          mapped === "exercises" ||
          mapped.startsWith("exercises.")
        ) {
          setNestedError(form, mapped, message);
        } else {
          unhandled.push(message);
        }
      }

      if (entries.length === 0 || unhandled.length > 0) {
        setSubmitError(
          unhandled[0] ??
            (entries.length === 0
              ? error.message
              : "Проверьте поля формы и попробуйте снова."),
        );
      }
    }
  }

  function fieldProps(path: FieldPath<WorkoutFormValues>) {
    const message = fieldError(form.formState.errors, path);

    return {
      "aria-invalid": Boolean(message),
      "aria-describedby": message ? `${path}-error` : undefined,
    } as const;
  }

  function errorText(path: FieldPath<WorkoutFormValues>) {
    const message = fieldError(form.formState.errors, path);

    return message ? (
      <p id={`${path}-error`} className="field-error">
        {message}
      </p>
    ) : null;
  }

  const exercisesRootError =
    fieldError(form.formState.errors, "exercises") ??
    (form.formState.errors.exercises as { message?: string } | undefined)?.message;

  const previewWeight = preview.data?.calculation.weight_kg ?? null;
  const canIgnoreIncline =
    type === "run" &&
    preview.data?.calculation.calorie_method === "unsupported_incline" &&
    inclineText.trim() !== "" &&
    Number(inclineText) > 0;
  const hasBikeSpeed = distanceText.trim() !== "" || speedText.trim() !== "";
  const cardioDistanceOptional =
    activityKind === "stationary_bike" || activityKind === "elliptical";

  return (
    <Modal
      title={
        state.mode === "edit"
          ? "Редактировать тренировку"
          : state.mode === "repeat"
            ? "Повторить тренировку"
            : "Новая тренировка"
      }
      describedBy="workout-editor-subtitle"
      onClose={handleClose}
      closeDisabled={busy}
      className="workout-editor"
      headerAction={
        <button
          type="button"
          className="icon-button"
          aria-label="Закрыть тренировку"
          onClick={handleClose}
          disabled={busy}
        >
          ×
        </button>
      }
    >
      <form className="workout-editor-form" onSubmit={form.handleSubmit(onSubmit)} noValidate>
        <p id="workout-editor-subtitle" className="editor-subtitle">
          Каждый подход — часть твоего прогресса.
        </p>

        <div className="editor-type" role="group" aria-label="Тип тренировки">
          {(Object.keys(WORKOUT_TYPE_LABELS) as WorkoutType[]).map((value) => (
            <button
              key={value}
              type="button"
              className={type === value ? "selected" : undefined}
              aria-pressed={type === value}
              onClick={() => changeType(value)}
              disabled={busy}
            >
              {WORKOUT_TYPE_LABELS[value]}
            </button>
          ))}
        </div>

        <label>
          Название тренировки
          <input
            type="text"
            maxLength={100}
            placeholder="Например, верх тела"
            data-autofocus
            disabled={busy}
            {...fieldProps("name")}
            {...form.register("name")}
          />
        </label>
        {errorText("name")}

        <div className="field-grid">
          <label>
            Дата
            <input
              type="date"
              disabled={busy}
              {...fieldProps("date")}
              {...form.register("date")}
            />
          </label>
          <label>
            Длительность, мин
            <input
              type="text"
              inputMode="numeric"
              placeholder="55"
              disabled={busy}
              {...fieldProps("duration_minutes")}
              {...form.register("duration_minutes")}
            />
          </label>
          {isStrength ? (
            <label>
              Дополнительные секунды
              <input
                type="text"
                inputMode="numeric"
                placeholder="0"
                disabled={busy}
                {...fieldProps("extra_seconds")}
                {...form.register("extra_seconds")}
              />
            </label>
          ) : null}
        </div>
        {errorText("duration_minutes")}
        {isStrength ? errorText("extra_seconds") : null}

        <section className="calorie-card" aria-labelledby="calorie-mode-title">
          <div className="calorie-mode-head">
            <h3 id="calorie-mode-title">Расчёт калорий</h3>
            <p>Расход сверх условного покоя. Пищевая норма от него не растёт.</p>
          </div>
          <div
            className="calorie-mode-switch"
            role="radiogroup"
            aria-label="Способ указания калорий"
          >
            <button
              type="button"
              role="radio"
              aria-checked={watched.calories_mode === "auto"}
              className={watched.calories_mode === "auto" ? "selected" : undefined}
              onClick={() => changeMode("auto")}
              disabled={busy}
            >
              Рассчитать автоматически
            </button>
            <button
              type="button"
              role="radio"
              aria-checked={watched.calories_mode === "manual"}
              className={watched.calories_mode === "manual" ? "selected" : undefined}
              onClick={() => changeMode("manual")}
              disabled={busy}
            >
              Указать вручную
            </button>
          </div>
          {isAuto ? (
            <>
              <div className="calorie-weight-row">
                <label>
                  Вес для расчёта, кг
                  <input
                    type="text"
                    inputMode="decimal"
                    placeholder={
                      previewWeight === null
                        ? "Из цели или профиля"
                        : formatWeight(previewWeight)
                    }
                    disabled={busy}
                    {...fieldProps("calorie_weight_kg")}
                    {...form.register("calorie_weight_kg")}
                  />
                </label>
                <p className="calorie-hint">
                  {previewWeight !== null
                    ? `Сейчас используется ${formatWeight(previewWeight)} кг${
                        preview.data?.calculation.weight_source
                          ? ` — ${WEIGHT_SOURCE_LABELS[preview.data.calculation.weight_source]}`
                          : ""
                      }.`
                    : "Вес подставится из цели на дату или профиля."}{" "}
                  Поле можно изменить только для этой тренировки.
                </p>
              </div>
              {errorText("calorie_weight_kg")}

              {isStrength ? (
                <label className="calorie-select">
                  Характер силовой тренировки
                  <select disabled={busy} {...form.register("strength_character")}>
                    <option value="">Выберите характер</option>
                    {STRENGTH_CHARACTER_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label} — {option.description}
                      </option>
                    ))}
                  </select>
                </label>
              ) : null}
              {isStrength ? errorText("strength_character") : null}

              <CaloriePreviewPanel
                preview={isAuto ? preview.data : null}
                loading={isAuto && preview.isLoading}
                error={isAuto ? preview.error : null}
                onRetry={preview.reload}
                onManual={() => changeMode("manual")}
                onIgnoreIncline={ignoreIncline}
                canIgnoreIncline={canIgnoreIncline}
              />
              <div className="calorie-actions">
                <button
                  type="button"
                  className="text-btn"
                  onClick={handleRecalculate}
                  disabled={busy || !previewValid}
                >
                  Рассчитать заново
                </button>
              </div>
              {errorText("incline_ignored")}
            </>
          ) : (
            <>
              <label className="calorie-manual">
                Калории активности
                <input
                  type="text"
                  inputMode="numeric"
                  placeholder="ккал, можно оставить пустым"
                  disabled={busy}
                  {...fieldProps("calories")}
                  {...form.register("calories")}
                />
              </label>
              {errorText("calories")}
              <p className="calorie-hint">
                Указано вручную. Автоматический расчёт не перезапишет это
                значение; 0 — тоже допустимое число.
              </p>
            </>
          )}
        </section>

        {isStrength ? (
          <div className="strength-fields">
            <div className="exercise-list">
              {exerciseFields.map((field, index) => (
                <ExerciseEditor
                  key={field.id}
                  form={form}
                  index={index}
                  busy={busy}
                  onRemove={() => removeExercise(index)}
                />
              ))}
            </div>
            {exercisesRootError ? (
              <p className="field-error" role="alert">
                {exercisesRootError}
              </p>
            ) : null}
            <button
              type="button"
              className="add-meal"
              onClick={() => appendExercise(emptyWorkoutExercise())}
              disabled={busy}
            >
              ＋ Добавить упражнение
            </button>
          </div>
        ) : (
          <div className="running-fields">
            {type === "cardio" ? (
              <>
                <label>
                  Вид кардио
                  <select disabled={busy} {...form.register("activity_kind")}>
                    <option value="">Выберите вид занятия</option>
                    {ACTIVITY_KIND_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label} — {option.description}
                      </option>
                    ))}
                  </select>
                </label>
                {errorText("activity_kind")}
                {watched.activity_kind === "outdoor_bike" ? (
                  <>
                    {hasBikeSpeed ? (
                      <p className="calorie-hint">
                        MET выбирается по скорости. Первая версия предполагает
                        ровную дорогу и велосипед без электропомощи.
                      </p>
                    ) : (
                      <label>
                        Нагрузка без скорости
                        <select
                          disabled={busy}
                          {...form.register("cardio_intensity")}
                        >
                          <option value="">Выберите нагрузку</option>
                          <option value="easy">Лёгкая</option>
                          <option value="moderate">Умеренная</option>
                          <option value="vigorous">Интенсивная</option>
                        </select>
                      </label>
                    )}
                    {errorText("cardio_intensity")}
                  </>
                ) : null}
                {watched.activity_kind === "stationary_bike" ? (
                  <>
                    <label>
                      Мощность, Вт
                      <input
                        type="text"
                        inputMode="numeric"
                        placeholder="Необязательно"
                        disabled={busy}
                        {...fieldProps("power_watts")}
                        {...form.register("power_watts")}
                      />
                    </label>
                    {errorText("power_watts")}
                    {watched.power_watts.trim() === "" ? (
                      <label>
                        Без мощности
                        <select
                          disabled={busy}
                          {...form.register("cardio_intensity")}
                        >
                          <option value="">Выберите вариант</option>
                          <option value="general">Общая тренировка</option>
                        </select>
                      </label>
                    ) : null}
                    {errorText("cardio_intensity")}
                  </>
                ) : null}
                {watched.activity_kind === "elliptical" ? (
                  <>
                    <label>
                      Нагрузка
                      <select
                        disabled={busy}
                        {...form.register("cardio_intensity")}
                      >
                        <option value="">Выберите нагрузку</option>
                        <option value="moderate">Умеренная</option>
                        <option value="vigorous">Интенсивная</option>
                      </select>
                    </label>
                    {errorText("cardio_intensity")}
                  </>
                ) : null}
                {watched.activity_kind === "other" ? (
                  <p className="calorie-hint">
                    Для этого вида кардио автоматический расчёт не поддерживается.
                    Можно сохранить тренировку без калорий или указать их вручную.
                  </p>
                ) : null}
              </>
            ) : null}

            <label>
              Как записать кардио
              <select disabled={busy} {...form.register("input_mode")}>
                <option value="distance">Дистанция и время</option>
                <option value="speed">Скорость и время</option>
              </select>
            </label>

            <div className="field-grid">
              {inputMode === "distance" ? (
                <label>
                  Дистанция, км{cardioDistanceOptional ? " (необязательно)" : ""}
                  <input
                    type="text"
                    inputMode="decimal"
                    placeholder={cardioDistanceOptional ? "Необязательно" : "5"}
                    disabled={busy}
                    {...fieldProps("distance_km")}
                    {...form.register("distance_km")}
                  />
                </label>
              ) : (
                <label>
                  Скорость, км/ч{cardioDistanceOptional ? " (необязательно)" : ""}
                  <input
                    type="text"
                    inputMode="decimal"
                    placeholder={cardioDistanceOptional ? "Необязательно" : "10"}
                    disabled={busy}
                    {...fieldProps("speed_kmh")}
                    {...form.register("speed_kmh")}
                  />
                </label>
              )}
              <label>
                Дополнительные секунды
                <input
                  type="text"
                  inputMode="numeric"
                  placeholder="0"
                  disabled={busy}
                  {...fieldProps("extra_seconds")}
                  {...form.register("extra_seconds")}
                />
              </label>
            </div>
            {errorText("distance_km")}
            {errorText("speed_kmh")}

            <p
              className={
                calculatedDistanceExceedsLimit ? "field-error" : "run-note"
              }
            >
              {inputMode === "speed"
                ? distance !== null
                  ? calculatedDistanceExceedsLimit
                    ? `Расчётная дистанция: ${formatNumber(distance)} км — больше допустимых 1000 км.`
                    : `Расчётная дистанция: ${formatNumber(distance)} км.`
                  : "Укажи скорость и длительность — дистанция рассчитается автоматически."
                : "Укажи дистанцию и длительность. Скорость и темп рассчитаются автоматически."}
            </p>

            <div
              className={type === "cardio" ? "run-results two" : "run-results"}
              aria-live="polite"
            >
              <div>
                <b>{metrics ? formatNumber(metrics.speed_kmh) : "—"}</b>
                <span>км/ч · скорость</span>
              </div>
              {type === "run" ? (
                <div>
                  <b>{metrics ? metrics.pace : "—"}</b>
                  <span>мин/км · темп</span>
                </div>
              ) : null}
              <div>
                <b>
                  {Number.isFinite(totalSeconds) && totalSeconds > 0
                    ? `${Math.floor(totalSeconds / 60)}:${String(totalSeconds % 60).padStart(2, "0")}`
                    : "—"}
                </b>
                <span>время</span>
              </div>
            </div>

            <div className="field-grid">
              <label>
                Средний пульс, уд/мин
                <input
                  type="text"
                  inputMode="numeric"
                  placeholder="Необязательно"
                  disabled={busy}
                  {...fieldProps("pulse")}
                  {...form.register("pulse")}
                />
              </label>
              {type === "run" ? (
                <label>
                  Уклон дорожки, %
                  <input
                    type="text"
                    inputMode="decimal"
                    placeholder="0 — ровно"
                    disabled={busy}
                    {...fieldProps("incline_percent")}
                    {...form.register("incline_percent")}
                  />
                </label>
              ) : null}
            </div>
            {errorText("pulse")}
            {errorText("incline_percent")}
            {errorText("extra_seconds")}

            {type === "run" ? (
              <>
                <label>
                  Поверхность
                  <select disabled={busy} {...form.register("run_surface")}>
                    {(
                      Object.keys(RUN_SURFACE_LABELS) as Array<
                        keyof typeof RUN_SURFACE_LABELS
                      >
                    ).map((surface) => (
                      <option key={surface} value={surface}>
                        {RUN_SURFACE_LABELS[surface]}
                      </option>
                    ))}
                  </select>
                </label>
                {errorText("run_surface")}
                <p className="calorie-hint">
                  Первая версия рассчитывает только ровную поверхность и уклон 0%.
                  Пульс не корректирует MET; интервалы оцениваются по средней
                  скорости.
                </p>
              </>
            ) : null}
          </div>
        )}

        <div className="field-grid">
          <label>
            Самочувствие
            <select disabled={busy} {...form.register("feeling")}>
              <option value="">Не указывать</option>
              {(Object.keys(FEELING_LABELS) as Array<keyof typeof FEELING_LABELS>).map(
                (value) => (
                  <option key={value} value={value}>
                    {FEELING_LABELS[value]}
                  </option>
                ),
              )}
            </select>
          </label>
        </div>

        <label className="editor-note">
          Заметка
          <textarea
            maxLength={2000}
            placeholder="Что получилось и что хочется запомнить"
            disabled={busy}
            {...fieldProps("note")}
            {...form.register("note")}
          />
        </label>
        {errorText("note")}

        {submitError ? (
          <p className="error" role="alert">
            {submitError}
          </p>
        ) : null}

        <div className="editor-footer">
          <button
            type="button"
            className="secondary"
            onClick={handleClose}
            disabled={busy}
          >
            Отмена
          </button>
          <button type="submit" className="primary" disabled={busy}>
            {busy ? "Сохраняем…" : "Сохранить тренировку"}
          </button>
        </div>
      </form>
    </Modal>
  );
}

function emptyWorkoutExercise(): WorkoutExerciseForm {
  return emptyExercise();
}
