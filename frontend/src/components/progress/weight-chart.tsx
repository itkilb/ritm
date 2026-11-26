import { useMemo } from "react";

import { formatWeight, parseIsoDate } from "@/lib/date";
import type { WeightAveragePoint, WeightPoint } from "@/lib/progress-api";

import { useChartWidth } from "./use-chart-width";

type Point = { x: number; y: number };
type AverageMarker = Point & { date: string; weight: number };

const DESKTOP_HEIGHT = 250;
const COMPACT_HEIGHT = 210;
const COMPACT_BREAKPOINT = 480;
const DESKTOP_PADDING = { top: 18, right: 18, bottom: 34, left: 44 };
const COMPACT_PADDING = { top: 18, right: 12, bottom: 34, left: 42 };

function chartLayout(width: number) {
  const resolved = Math.max(1, Math.round(width));
  const compact = resolved < COMPACT_BREAKPOINT;

  return {
    width: resolved,
    height: compact ? COMPACT_HEIGHT : DESKTOP_HEIGHT,
    padding: compact ? COMPACT_PADDING : DESKTOP_PADDING,
  };
}

const AXIS_WEIGHT_FORMAT = new Intl.NumberFormat("ru-RU", {
  maximumFractionDigits: 1,
});

function formatAxisWeight(value: number): string {
  return AXIS_WEIGHT_FORMAT.format(value);
}

function dayNumber(date: string): number | null {
  const parts = parseIsoDate(date);

  if (!parts) {
    return null;
  }

  return Math.floor(Date.UTC(parts.year, parts.month - 1, parts.day) / 86_400_000);
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

export function WeightChart({
  entries,
  average,
  target,
}: {
  entries: WeightPoint[];
  average: WeightAveragePoint[];
  target: number | null;
}) {
  const { containerRef, width } = useChartWidth<HTMLDivElement>();
  const layout = useMemo(() => chartLayout(width), [width]);

  const geometry = useMemo(() => {
    if (entries.length === 0) {
      return null;
    }

    const { padding } = layout;
    const days = entries
      .map((entry) => dayNumber(entry.date))
      .filter((value): value is number => value !== null);

    if (days.length === 0) {
      return null;
    }

    const minDay = Math.min(...days);
    const maxDay = Math.max(...days);
    const span = Math.max(1, maxDay - minDay);
    const values = [
      ...entries.map((entry) => entry.weight_kg),
      ...average.map((point) => point.weight_kg),
      ...(target === null ? [] : [target]),
    ];
    let minValue = Math.min(...values);
    let maxValue = Math.max(...values);

    if (maxValue - minValue < 1) {
      minValue -= 0.5;
      maxValue += 0.5;
    } else {
      const pad = (maxValue - minValue) * 0.12;
      minValue -= pad;
      maxValue += pad;
    }

    const innerWidth = layout.width - padding.left - padding.right;
    const innerHeight = layout.height - padding.top - padding.bottom;

    function toPoint(date: string, value: number): Point | null {
      const day = dayNumber(date);

      if (day === null) {
        return null;
      }

      return {
        x:
          padding.left +
          (entries.length === 1
            ? innerWidth / 2
            : ((day - minDay) / span) * innerWidth),
        y:
          padding.top +
          (1 - (value - minValue) / (maxValue - minValue)) * innerHeight,
      };
    }

    const actual = entries
      .map((entry) => toPoint(entry.date, entry.weight_kg))
      .filter((point): point is Point => point !== null);
    const moving = average
      .map((point) => {
        const coordinates = toPoint(point.date, point.weight_kg);

        return coordinates === null
          ? null
          : { ...coordinates, date: point.date, weight: point.weight_kg };
      })
      .filter((point): point is AverageMarker => point !== null);
    const targetY =
      target === null
        ? null
        : padding.top +
          (1 - (target - minValue) / (maxValue - minValue)) * innerHeight;

    return {
      actual,
      moving,
      targetY,
      minValue,
      maxValue,
      firstDate: entries[0].date,
      lastDate: entries[entries.length - 1].date,
    };
  }, [entries, average, target, layout]);

  if (entries.length === 0 || geometry === null) {
    return (
      <div className="progress-chart-empty" role="status">
        Добавьте первый замер — здесь появится график.
      </div>
    );
  }

  const line = (points: Point[]): string =>
    points.map((point) => `${point.x},${point.y}`).join(" ");

  return (
    <figure className="progress-chart">
      <figcaption className="sr-only">
        График веса в килограммах: фактические замеры, скользящее среднее за 7
        дней и целевой вес.
      </figcaption>
      <div className="progress-chart-legend">
        <span className="legend-actual">Фактический вес, кг</span>
        <span className="legend-average">Среднее за 7 дней, кг</span>
        {target !== null ? <span className="legend-target">Цель, кг</span> : null}
      </div>
      <div className="progress-chart-scroll" ref={containerRef}>
        <svg
          viewBox={`0 0 ${layout.width} ${layout.height}`}
          role="img"
          aria-label="График веса"
          preserveAspectRatio="xMidYMid meet"
        >
          <title>График веса</title>
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
                  className="chart-axis-text chart-axis-value"
                >
                  {formatAxisWeight(value)}
                </text>
              </g>
            );
          })}

          {geometry.targetY !== null ? (
            <line
              x1={layout.padding.left}
              y1={geometry.targetY}
              x2={layout.width - layout.padding.right}
              y2={geometry.targetY}
              className="chart-target-line"
            />
          ) : null}

          {geometry.actual.length > 1 ? (
            <polyline points={line(geometry.actual)} className="chart-actual-line" />
          ) : null}
          {geometry.moving.length > 1 ? (
            <polyline points={line(geometry.moving)} className="chart-average-line" />
          ) : null}

          {geometry.actual.map((point, index) => (
            <circle
              key={`${entries[index]?.id ?? index}-${point.x}`}
              cx={point.x}
              cy={point.y}
              r={4}
              className="chart-actual-point"
            >
              <title>
                {entries[index]?.date}: {formatWeight(entries[index]?.weight_kg ?? 0)} кг
              </title>
            </circle>
          ))}

          {geometry.moving.map((point) => (
            <circle
              key={`average-${point.date}-${point.x}`}
              cx={point.x}
              cy={point.y}
              r={geometry.moving.length === 1 ? 5 : 3.5}
              className="chart-average-point"
            >
              <title>
                Среднее за 7 дней · {point.date}: {formatWeight(point.weight)} кг
              </title>
            </circle>
          ))}

          <text
            x={layout.padding.left}
            y={layout.height - 10}
            textAnchor="start"
            className="chart-axis-text"
          >
            {shortDate(geometry.firstDate)}
          </text>
          {geometry.lastDate !== geometry.firstDate ? (
            <text
              x={layout.width - layout.padding.right}
              y={layout.height - 10}
              textAnchor="end"
              className="chart-axis-text"
            >
              {shortDate(geometry.lastDate)}
            </text>
          ) : null}
        </svg>
      </div>
      <p className="progress-chart-note">
        Среднее считается по календарным 7 дням только при двух и более замерах
        в окне; пропущенные дни не интерполируются. Всего точек:{" "}
        {entries.length}.
      </p>
    </figure>
  );
}
