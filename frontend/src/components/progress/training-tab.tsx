import { useState } from "react";
import { Link } from "react-router-dom";

import { ExerciseHistoryChart } from "@/components/progress/exercise-history-chart";
import {
  EXERCISE_MEASUREMENT_LABELS,
  type Exercise,
} from "@/lib/exercise-api";
import { formatDistance, formatNumber, formatWeekRange, formatWeight } from "@/lib/date";
import type {
  ExerciseHistory,
  ProgressCardioKind,
  ProgressWorkouts,
} from "@/lib/progress-api";
import { ACTIVITY_KIND_LABELS } from "@/lib/workout-form";

type WeekMetric = "count" | "minutes";

const CARDIO_KIND_LABELS: Record<ProgressCardioKind, string> = {
  ...ACTIVITY_KIND_LABELS,
  unknown: "Вид не указан",
};

function formatSeconds(seconds: number | null): string {
  if (seconds === null) {
    return "—";
  }

  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`;
}

function historyMetric(point: ExerciseHistory["points"][number]): string {
  if (point.mode === "weighted" || point.mode === "bodyweight") {
    return `${formatWeight(point.weight_kg ?? 0)} кг × ${point.reps ?? "—"}`;
  }

  if (point.mode === "reps") {
    return `${point.reps ?? 0} повторений`;
  }

  return formatSeconds(point.seconds);
}

function bestMetric(history: ExerciseHistory): string {
  const { summary, exercise } = history;

  if (
    exercise.measurement_type === "weighted" ||
    exercise.measurement_type === "bodyweight"
  ) {
    return summary.best_weight_kg === null
      ? "—"
      : `${formatWeight(summary.best_weight_kg)} кг × ${summary.best_reps ?? "—"}`;
  }

  if (exercise.measurement_type === "reps") {
    return summary.best_reps === null ? "—" : `${summary.best_reps} повторений`;
  }

  return formatSeconds(summary.best_seconds);
}

export function TrainingTab({
  workouts,
  exercises,
  selectedExerciseId,
  onSelectExercise,
  history,
  historyLoading,
}: {
  workouts: ProgressWorkouts;
  exercises: Exercise[];
  selectedExerciseId: string | null;
  onSelectExercise: (id: string | null) => void;
  history: ExerciseHistory | null;
  historyLoading: boolean;
}) {
  const [weekMetric, setWeekMetric] = useState<WeekMetric>("count");
  const maxCount = Math.max(1, ...workouts.weeks.map((week) => week.count));
  const maxMinutes = Math.max(1, ...workouts.weeks.map((week) => week.minutes));
  const weekMax = weekMetric === "count" ? maxCount : maxMinutes;

  return (
    <section className="progress-tab" aria-label="Тренировки">
      <div className="progress-cards">
        <article className="progress-card">
          <span>Тренировок за период</span>
          <strong>{formatNumber(workouts.totals.count)}</strong>
          <small>Все типы: силовые, бег и другое кардио</small>
        </article>
        <article className="progress-card">
          <span>Минут движения</span>
          <strong>{formatNumber(workouts.totals.minutes)}</strong>
          <small>Сумма длительности всех тренировок</small>
        </article>
        <article className="progress-card">
          <span>Км бега</span>
          <strong>{formatDistance(workouts.run.distance_km)}</strong>
          <small>Только бег; другое кардио считается отдельно</small>
        </article>
      </div>

      <div className="progress-card">
        <div className="progress-section-head">
          <div>
            <h2>По неделям</h2>
            <p>
              ISO-недели пн–вс. Недели без тренировок показаны с нулём, а не
              пропущены.
            </p>
          </div>
          {workouts.totals.uncalculated_count > 0 ? (
            <span className="progress-badge">
              Без калорий: {workouts.totals.uncalculated_count}
            </span>
          ) : null}
        </div>
        <div
          className="week-metric-switch"
          role="group"
          aria-label="Метрика недельного графика"
        >
          <button
            type="button"
            aria-pressed={weekMetric === "count"}
            className={weekMetric === "count" ? "selected" : undefined}
            onClick={() => setWeekMetric("count")}
          >
            Количество
          </button>
          <button
            type="button"
            aria-pressed={weekMetric === "minutes"}
            className={weekMetric === "minutes" ? "selected" : undefined}
            onClick={() => setWeekMetric("minutes")}
          >
            Минуты
          </button>
        </div>
        <ul className="week-progress-list" aria-label="Тренировки по неделям">
          {workouts.weeks.map((week) => {
            const value = weekMetric === "count" ? week.count : week.minutes;

            return (
              <li key={week.week_start}>
                <span className="week-progress-label">
                  {formatWeekRange(week.week_start, week.week_end)}
                </span>
                <span
                  className="week-progress-bar"
                  aria-hidden="true"
                  data-testid={`week-bar-${week.week_start}`}
                >
                  <i
                    data-metric={weekMetric}
                    style={{
                      width: `${Math.round((value / weekMax) * 100)}%`,
                    }}
                  />
                </span>
                <span className="week-progress-value">
                  {weekMetric === "count"
                    ? `${week.count} · ${formatNumber(week.minutes)} мин`
                    : `${formatNumber(week.minutes)} мин · ${week.count}`}
                </span>
              </li>
            );
          })}
        </ul>
      </div>

      <div className="progress-two-col">
        <article className="progress-card">
          <h2>Бег</h2>
          <dl className="progress-metrics">
            <div>
              <dt>Тренировок</dt>
              <dd>{workouts.run.count}</dd>
            </div>
            <div>
              <dt>Дистанция</dt>
              <dd>{formatDistance(workouts.run.distance_km)} км</dd>
            </div>
            <div>
              <dt>Время</dt>
              <dd>{formatNumber(workouts.run.minutes)} мин</dd>
            </div>
            <div>
              <dt>Темп</dt>
              <dd>{workouts.run.pace === null ? "—" : `${workouts.run.pace} мин/км`}</dd>
            </div>
          </dl>
          <p className="progress-note">
            Темп считается только по бегу с положительной дистанцией; время
            legacy-бега без дистанции в него не входит.
          </p>
        </article>

        <article className="progress-card">
          <h2>Другое кардио</h2>
          {workouts.cardio.kinds.length === 0 ? (
            <div className="progress-empty">
              <b>Нет кардиотренировок</b>
              <p>За выбранный период другие виды кардио не записаны.</p>
            </div>
          ) : (
            <div className="cardio-kinds">
              {workouts.cardio.kinds.map((kind) => (
                <article className="cardio-kind" key={kind.activity_kind}>
                  <h3>{CARDIO_KIND_LABELS[kind.activity_kind] ?? kind.activity_kind}</h3>
                  <dl className="progress-metrics">
                    <div>
                      <dt>Тренировок</dt>
                      <dd>{kind.count}</dd>
                    </div>
                    <div>
                      <dt>Время</dt>
                      <dd>{formatNumber(kind.minutes)} мин</dd>
                    </div>
                    <div>
                      <dt>Дистанция</dt>
                      <dd>{formatDistance(kind.distance_km)} км</dd>
                    </div>
                    {kind.speed_kmh === null ? null : (
                      <div>
                        <dt>Скорость</dt>
                        <dd>{formatNumber(kind.speed_kmh)} км/ч</dd>
                      </div>
                    )}
                  </dl>
                </article>
              ))}
            </div>
          )}
          <p className="progress-note">
            Велосипед, велотренажёр, эллипс, другое кардио и записи без вида
            считаются отдельно. Скорость показывается только для велосипеда,
            где дистанция сопоставима; остальные виды не смешиваются.
          </p>
        </article>
      </div>

      <div className="progress-card">
        <div className="progress-section-head">
          <div>
            <h2>История упражнения</h2>
            <p>
              Только записи, связанные со справочником. Legacy-названия не
              объединяются автоматически.
            </p>
          </div>
          <label className="history-select">
            <span className="sr-only">Упражнение для истории</span>
            <select
              aria-label="Упражнение для истории"
              value={selectedExerciseId ?? ""}
              onChange={(event) => onSelectExercise(event.target.value || null)}
            >
              <option value="">Выберите упражнение</option>
              {exercises.map((exercise) => (
                <option key={exercise.id} value={exercise.id}>
                  {exercise.name} ·{" "}
                  {EXERCISE_MEASUREMENT_LABELS[exercise.measurement_type]}
                </option>
              ))}
            </select>
          </label>
        </div>

        {selectedExerciseId === null ? (
          <div className="progress-empty">
            <b>Выберите упражнение</b>
            <p>
              Покажем лучший подход в каждой тренировке: вес с повторениями,
              повторения или время — по типу упражнения.
            </p>
          </div>
        ) : historyLoading ? (
          <p className="progress-note" role="status">
            Загружаем историю упражнения…
          </p>
        ) : history === null || history.points.length === 0 ? (
          <div className="progress-empty">
            <b>Пока нет связанных записей</b>
            <p>
              Свяжите упражнение в редакторе тренировки или выберите другой
              период.
            </p>
          </div>
        ) : (
          <>
            <div className="history-summary">
              <span>Лучший результат за период</span>
              <strong>{bestMetric(history)}</strong>
              <small>
                {history.exercise.name} ·{" "}
                {EXERCISE_MEASUREMENT_LABELS[history.exercise.measurement_type]} ·{" "}
                {history.exercise.muscle_group}
              </small>
            </div>
            <ExerciseHistoryChart history={history} />
            <ul className="history-points" aria-label="Точки истории упражнения">
              {history.points.map((point) => (
                <li key={`${point.workout_id}-${point.date}-${point.mode}`}>
                  <span>{point.date}</span>
                  <b>{historyMetric(point)}</b>
                  <span className="history-point-meta">
                    {point.workout_name}
                  </span>
                  <Link
                    className="text-btn"
                    to={`/workouts?workout=${encodeURIComponent(point.workout_id)}`}
                  >
                    Открыть тренировку
                  </Link>
                </li>
              ))}
            </ul>
            {history.exercise.measurement_type === "weighted" ||
            history.exercise.measurement_type === "bodyweight" ? (
              <p className="progress-note">
                Лучший подход — максимальный вес, при равенстве берётся больше
                повторений. 0 кг — допустимый результат, а не отсутствие данных.
              </p>
            ) : null}
          </>
        )}
      </div>
    </section>
  );
}
