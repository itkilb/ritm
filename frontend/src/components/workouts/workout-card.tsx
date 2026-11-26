import { useState } from "react";

import { WorkoutIcon } from "@/components/workouts/workout-icon";
import type { Workout } from "@/lib/workout-api";
import {
  ACTIVITY_KIND_LABELS,
  FEELING_LABELS,
  RUN_SURFACE_LABELS,
  STRENGTH_CHARACTER_LABELS,
  WEIGHT_SOURCE_LABELS,
  WORKOUT_TYPE_LABELS,
} from "@/lib/workout-form";
import {
  formatDistance,
  formatIsoDate,
  formatNumber,
  formatWeight,
} from "@/lib/date";

function plural(
  count: number,
  one: string,
  few: string,
  many: string,
): string {
  const mod10 = count % 10;
  const mod100 = count % 100;

  if (mod10 === 1 && mod100 !== 11) {
    return one;
  }

  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return few;
  }

  return many;
}

function workoutMinutes(seconds: number): string {
  return new Intl.NumberFormat("ru-RU", {
    maximumFractionDigits: 1,
  }).format(seconds / 60);
}

function setLabel(set: Workout["exercises"][number]["sets"][number], mode: string): string {
  if (mode === "time") {
    return `${set.seconds ?? 0} сек`;
  }

  if (mode === "reps") {
    return `${set.reps ?? 0} повторений`;
  }

  if (mode === "bodyweight") {
    return `+${formatNumber(set.weight_kg ?? 0)} кг × ${set.reps ?? 0}`;
  }

  return `${formatNumber(set.weight_kg ?? 0)} кг × ${set.reps ?? 0}`;
}

function calorieHeadline(workout: Workout): string {
  if (workout.calories === null) {
    return "Калории не рассчитаны";
  }

  const prefix = workout.calories_mode === "auto" ? "≈ " : "";

  return `${prefix}${formatNumber(workout.calories)} ккал активности`;
}

function durationText(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined || seconds <= 0) {
    return "—";
  }

  const minutes = Math.floor(seconds / 60);
  const rest = seconds % 60;

  return rest === 0 ? `${minutes} мин` : `${minutes} мин ${rest} с`;
}

export function WorkoutCard({
  workout,
  deletePending,
  onEdit,
  onRepeat,
  onDelete,
}: {
  workout: Workout;
  deletePending: boolean;
  onEdit: () => void;
  onRepeat: () => void;
  onDelete: () => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const detailsId = `workout-details-${workout.id}`;
  const isCardio = workout.type !== "strength";
  const explanation = workout.calorie_explanation;
  const calculated = workout.calories_status === "calculated";

  return (
    <section className="card workout-card" aria-labelledby={`workout-${workout.id}-title`}>
      <div className="workout-card-heading">
        <span
          className={workout.type === "strength" ? "workout-icon" : "workout-icon running"}
          aria-hidden="true"
        >
          <WorkoutIcon
            name={workout.type === "strength" ? "strength" : workout.type === "run" ? "run" : "time"}
          />
        </span>
        <div>
          <span className="workout-type">{WORKOUT_TYPE_LABELS[workout.type]}</span>
          <h3 id={`workout-${workout.id}-title`}>{workout.name}</h3>
        </div>
        <span className="chip">{formatIsoDate(workout.date, { day: "numeric", month: "long" })}</span>
      </div>

      <div className="workout-metrics">
        {workout.type === "strength" ? (
          <>
            <div>
              <b>{workoutMinutes(workout.duration_seconds)}</b>
              <span>минут</span>
            </div>
            <div>
              <b>{workout.exercise_count}</b>
              <span>
                {plural(
                  workout.exercise_count,
                  "упражнение",
                  "упражнения",
                  "упражнений",
                )}
              </span>
            </div>
            <div>
              <b>{workout.set_count}</b>
              <span>
                {plural(workout.set_count, "подход", "подхода", "подходов")}
              </span>
            </div>
          </>
        ) : (
          <>
            <div>
              <b>{workout.distance_km === null ? "—" : formatDistance(workout.distance_km)}</b>
              <span>км</span>
            </div>
            <div>
              <b>{workoutMinutes(workout.duration_seconds)}</b>
              <span>минут</span>
            </div>
            <div>
              <b>
                {workout.type === "run"
                  ? workout.pace ?? "—"
                  : workout.speed_kmh === null
                    ? "—"
                    : formatNumber(workout.speed_kmh)}
              </b>
              <span>{workout.type === "run" ? "мин/км" : "км/ч"}</span>
            </div>
          </>
        )}
      </div>

      <div
        className={
          workout.calories === null
            ? "workout-calories-line missing"
            : "workout-calories-line"
        }
      >
        <b>{calorieHeadline(workout)}</b>
        <span>
          {workout.calories_mode === "auto"
            ? workout.calories_status === "calculated"
              ? "автоматический расчёт"
              : "автоматический расчёт недоступен"
            : workout.calories_status === "legacy"
              ? "ручной ввод, старая запись"
              : "ручной ввод"}
        </span>
        {calculated && workout.gross_kcal !== null ? (
          <span>≈ {formatNumber(workout.gross_kcal)} ккал всего</span>
        ) : null}
      </div>

      {workout.type === "strength" && workout.exercises.length > 0 ? (
        <div className="exercise-preview">
          {workout.exercises.map((exercise) => exercise.name).join(" · ")}
        </div>
      ) : null}

      <div id={detailsId} className="workout-details" hidden={!expanded}>
        {workout.type === "strength" ? (
          workout.exercises.map((exercise) => (
            <div className="saved-exercise" key={exercise.id}>
              <strong>{exercise.name}</strong>
              <p>
                {exercise.sets
                  .map((set, index) => `${index + 1}. ${setLabel(set, exercise.mode)}`)
                  .join(" · ")}
              </p>
            </div>
          ))
        ) : (
          <p className="workout-note">
            {workout.distance_km !== null
              ? `Дистанция: ${formatDistance(workout.distance_km)} км · `
              : ""}
            Скорость:{" "}
            {workout.speed_kmh === null ? "—" : `${formatNumber(workout.speed_kmh)} км/ч`}
            {workout.pulse ? ` · Пульс: ${workout.pulse}` : ""}
            {workout.type === "run" && workout.incline_percent !== null
              ? ` · Уклон: ${formatNumber(workout.incline_percent)}%`
              : ""}
          </p>
        )}
        {workout.activity_kind ? (
          <p className="training-small-muted">
            Вид кардио: {ACTIVITY_KIND_LABELS[workout.activity_kind]}
          </p>
        ) : null}
        {workout.strength_character ? (
          <p className="training-small-muted">
            Характер силовой: {STRENGTH_CHARACTER_LABELS[workout.strength_character]}
          </p>
        ) : null}
        {workout.type === "run" && workout.run_surface ? (
          <p className="training-small-muted">
            Поверхность: {RUN_SURFACE_LABELS[workout.run_surface]}
          </p>
        ) : null}
        {workout.note ? <p className="workout-note">{workout.note}</p> : null}
        {workout.feeling ? (
          <p className="training-small-muted">
            Самочувствие: {FEELING_LABELS[workout.feeling]}
          </p>
        ) : null}

        <div className="workout-calorie-details">
          <p className="training-small-muted">
            <b>Калории активности:</b> {calorieHeadline(workout)}
            {calculated && workout.gross_kcal !== null
              ? ` · общий расход: ≈ ${formatNumber(workout.gross_kcal)} ккал`
              : ""}
          </p>
          {calculated ? (
            <>
              <p className="training-small-muted">
                {explanation.category ?? "Категория"} · MET{" "}
                {formatNumber(explanation.met ?? 0)}
                {explanation.met_code ? ` (код ${explanation.met_code})` : ""} · вес{" "}
                {formatWeight(explanation.weight_kg ?? 0)} кг
                {explanation.weight_source
                  ? ` (${WEIGHT_SOURCE_LABELS[explanation.weight_source]})`
                  : ""}{" "}
                · {durationText(explanation.duration_seconds)}. Расход сверх
                условного покоя.
              </p>
              {explanation.catalog_url ? (
                <p className="training-small-muted">
                  Источник:{" "}
                  <a
                    href={explanation.catalog_url}
                    target="_blank"
                    rel="noreferrer"
                  >
                    {explanation.catalog}
                    {explanation.catalog_version
                      ? `, ${explanation.catalog_version}`
                      : ""}
                  </a>
                  .
                </p>
              ) : null}
            </>
          ) : null}
          {explanation.reason ? (
            <p className="training-small-muted">{explanation.reason}</p>
          ) : null}
          {explanation.warnings.map((warning) => (
            <p className="training-small-muted" key={warning}>
              {warning}
            </p>
          ))}
          {workout.calories_mode === "manual" ? (
            <p className="training-small-muted">
              Значение указано вручную и не перезаписывается автоматикой.
            </p>
          ) : null}
        </div>
        {isCardio && workout.distance_km === null ? (
          <p className="training-small-muted">Дистанция не указана.</p>
        ) : null}
      </div>

      <div className="workout-actions">
        <button
          type="button"
          className="text-btn"
          aria-expanded={expanded}
          aria-controls={detailsId}
          onClick={() => setExpanded((value) => !value)}
        >
          {expanded ? "Свернуть" : "Подробнее"}
        </button>
        <button type="button" className="text-btn" onClick={onEdit}>
          Изменить
        </button>
        <button type="button" className="text-btn" onClick={onRepeat}>
          Повторить
        </button>
        <button
          type="button"
          className="text-btn delete-workout"
          onClick={onDelete}
          disabled={deletePending}
        >
          {deletePending ? "Удаляем…" : "Удалить"}
        </button>
      </div>
    </section>
  );
}
