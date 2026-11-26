import { useMemo } from "react";
import { useNavigate } from "react-router-dom";

import { formatNumber, formatWeight, parseIsoDate } from "@/lib/date";
import type {
  ExerciseHistory,
  ExerciseHistoryPoint,
} from "@/lib/progress-api";

import { useChartWidth } from "./use-chart-width";

type MeasurementType = ExerciseHistory["exercise"]["measurement_type"];
type MetricPoint = { x: number; y: number };
type RenderedPoint = {
  point: ExerciseHistoryPoint;
  value: number;
  position: MetricPoint;
};

const DESKTOP_HEIGHT = 250;
const COMPACT_HEIGHT = 210;
const COMPACT_BREAKPOINT = 480;
const DESKTOP_PADDING = { top: 22, right: 20, bottom: 38, left: 52 };
const COMPACT_PADDING = { top: 18, right: 12, bottom: 36, left: 46 };
const SAME_DAY_GAP = 9;

function chartLayout(width: number) {
  const resolved = Math.max(1, Math.round(width));
  const compact = resolved < COMPACT_BREAKPOINT;

  return {
    width: resolved,
    height: compact ? COMPACT_HEIGHT : DESKTOP_HEIGHT,
    padding: compact ? COMPACT_PADDING : DESKTOP_PADDING,
  };
}

function dayNumber(date: string): number | null {
  const parts = parseIsoDate(date);

  if (!parts) {
    return null;
  }

  return Math.floor(
    Date.UTC(parts.year, parts.month - 1, parts.day) / 86_400_000,
  );
}

function metricValue(
  point: ExerciseHistoryPoint,
  type: MeasurementType,
): number | null {
  if (type === "reps") {
    return point.reps;
  }

  if (type === "time") {
    return point.seconds;
  }

  return point.weight_kg;
}

function unit(type: MeasurementType): string {
  if (type === "reps") {
    return "повторения";
  }

  if (type === "time") {
    return "секунды";
  }

  return "кг";
}

function metricText(value: number, type: MeasurementType): string {
  if (type === "reps") {
    return `${formatNumber(value)} повторений`;
  }

  if (type === "time") {
    return `${formatNumber(value)} сек`;
  }

  return `${formatWeight(value)} кг`;
}

function axisLabel(value: number, type: MeasurementType): string {
  if (type === "reps" || type === "time") {
    return String(Math.round(value));
  }

  return new Intl.NumberFormat("ru-RU", {
    maximumFractionDigits: 1,
  }).format(value);
}

function shortDate(date: string): string {
  const parts = parseIsoDate(date);

  if (!parts) {
    return date;
  }

  return new Intl.DateTimeFormat("ru-RU", {
    day: "numeric",
    month: "short",
    timeZone: "UTC",
  }).format(new Date(Date.UTC(parts.year, parts.month - 1, parts.day, 12)));
}

export function ExerciseHistoryChart({ history }: { history: ExerciseHistory }) {
  const navigate = useNavigate();
  const type = history.exercise.measurement_type;
  const { containerRef, width } = useChartWidth<HTMLDivElement>();
  const layout = useMemo(() => chartLayout(width), [width]);

  const geometry = useMemo(() => {
    const { padding } = layout;
    const prepared = history.points
      .map((point) => ({ point, value: metricValue(point, type) }))
      .filter(
        (entry): entry is { point: ExerciseHistoryPoint; value: number } =>
          entry.value !== null && Number.isFinite(entry.value),
      );

    if (prepared.length === 0) {
      return null;
    }

    const days = prepared
      .map((entry) => dayNumber(entry.point.date))
      .filter((value): value is number => value !== null);

    if (days.length === 0) {
      return null;
    }

    const minDay = Math.min(...days);
    const maxDay = Math.max(...days);
    const daySpan = maxDay - minDay;
    const values = prepared.map((entry) => entry.value);
    let minValue = Math.min(...values);
    let maxValue = Math.max(...values);

    if (maxValue - minValue < 0.000_001) {
      const pad =
        type === "reps" || type === "time"
          ? Math.max(1, Math.abs(maxValue) * 0.1)
          : 1;
      minValue -= pad;
      maxValue += pad;
    } else {
      const pad = (maxValue - minValue) * 0.12;
      minValue -= pad;
      maxValue += pad;
    }

    const innerWidth = layout.width - padding.left - padding.right;
    const innerHeight = layout.height - padding.top - padding.bottom;

    const dayCounts = new Map<number, number>();

    for (const day of days) {
      dayCounts.set(day, (dayCounts.get(day) ?? 0) + 1);
    }

    const daySeen = new Map<number, number>();
    const rendered: RenderedPoint[] = [];

    for (const entry of prepared) {
      const day = dayNumber(entry.point.date);

      if (day === null) {
        continue;
      }

      const count = dayCounts.get(day) ?? 1;
      const seen = daySeen.get(day) ?? 0;
      daySeen.set(day, seen + 1);

      const offset = count > 1 ? (seen - (count - 1) / 2) * SAME_DAY_GAP : 0;
      const baseX =
        daySpan === 0
          ? padding.left + innerWidth / 2
          : padding.left + ((day - minDay) / daySpan) * innerWidth;
      const x = Math.min(
        layout.width - padding.right,
        Math.max(padding.left, baseX + offset),
      );
      const y =
        padding.top +
        (1 - (entry.value - minValue) / (maxValue - minValue)) * innerHeight;

      rendered.push({
        point: entry.point,
        value: entry.value,
        position: { x, y },
      });
    }

    return {
      points: rendered,
      minValue,
      maxValue,
      firstDate: prepared[0].point.date,
      lastDate: prepared[prepared.length - 1].point.date,
      sameDayGroups: [...dayCounts.values()].filter((count) => count > 1).length,
    };
  }, [history.points, type, layout]);

  if (geometry === null) {
    return (
      <div className="progress-chart-empty" role="status">
        Для этого упражнения пока нет числовых точек за период.
      </div>
    );
  }

  const line = geometry.points
    .map((entry) => `${entry.position.x},${entry.position.y}`)
    .join(" ");

  return (
    <figure
      className="progress-chart history-chart"
      aria-label={`График истории упражнения ${history.exercise.name}`}
    >
      <figcaption className="progress-chart-caption">
        {history.exercise.name} · {unit(type)} · реальные даты тренировок
      </figcaption>
      <div className="progress-chart-scroll" ref={containerRef}>
        <svg
          viewBox={`0 0 ${layout.width} ${layout.height}`}
          role="group"
          aria-label={`Точки истории упражнения ${history.exercise.name}`}
          preserveAspectRatio="xMidYMid meet"
        >
          {[0, 0.5, 1].map((ratio) => {
            const y =
              layout.padding.top +
              ratio *
                (layout.height - layout.padding.top - layout.padding.bottom);
            const value =
              geometry.maxValue - ratio * (geometry.maxValue - geometry.minValue);

            return (
              <g key={ratio}>
                <line
                  x1={layout.padding.left}
                  y1={y}
                  x2={layout.width - layout.padding.right}
                  y2={y}
                  className="chart-grid-line"
                />
                <text
                  x={layout.padding.left - 8}
                  y={y + 4}
                  textAnchor="end"
                  className="chart-axis-text"
                >
                  {axisLabel(value, type)}
                </text>
              </g>
            );
          })}

          {geometry.points.length > 1 ? (
            <polyline points={line} className="chart-actual-line" />
          ) : null}

          {geometry.points.map((entry, index) => {
            const href = `/workouts?workout=${encodeURIComponent(entry.point.workout_id)}`;

            return (
              <a
                key={`${entry.point.workout_id}-${entry.point.date}-${index}`}
                href={href}
                className="history-chart-point-link"
                tabIndex={0}
                aria-label={`${entry.point.date}: ${metricText(entry.value, type)}. ${entry.point.workout_name}. Открыть тренировку`}
                onClick={(event) => {
                  event.preventDefault();
                  navigate(href);
                }}
              >
                <title>
                  {entry.point.date}: {metricText(entry.value, type)} —{" "}
                  {entry.point.workout_name}
                </title>
                <circle
                  cx={entry.position.x}
                  cy={entry.position.y}
                  r={5}
                  className="chart-actual-point"
                />
              </a>
            );
          })}

          <text
            x={layout.padding.left}
            y={layout.height - 12}
            textAnchor="start"
            className="chart-axis-text"
          >
            {shortDate(geometry.firstDate)}
          </text>
          {geometry.lastDate !== geometry.firstDate ? (
            <text
              x={layout.width - layout.padding.right}
              y={layout.height - 12}
              textAnchor="end"
              className="chart-axis-text"
            >
              {shortDate(geometry.lastDate)}
            </text>
          ) : null}
        </svg>
      </div>
      <p className="progress-chart-note">
        Каждая точка — отдельная тренировка. Несколько тренировок в один день
        показаны рядом и не теряются; нажмите на точку, чтобы открыть запись.
        {geometry.sameDayGroups > 0
          ? " Совпадения по дате: " + geometry.sameDayGroups + "."
          : ""}
      </p>
    </figure>
  );
}
