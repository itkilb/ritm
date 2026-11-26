import type { CSSProperties } from "react";

import { Skeleton } from "@/components/ui/skeleton";

import type { WeeklyData } from "@/lib/dashboard-api";
import {
  formatNumber,
  formatWeekRange,
  weekdayShort,
} from "@/lib/date";
import { cn } from "@/lib/utils";

function DayRing({
  progress,
  over,
  dayNumber,
}: {
  progress: number | null;
  over: boolean;
  dayNumber: string;
}) {
  const percent =
    progress === null ? 0 : Math.max(0, Math.min(100, progress * 100));
  const style = {
    "--progress": `${percent}%`,
    ...(over ? { "--ring": "#d8ae73" } : {}),
  } as CSSProperties;

  return (
    <span className="week-ring" style={style} aria-hidden="true">
      <span>{dayNumber}</span>
    </span>
  );
}

export function WeekCard({
  weekly,
  isLoading,
  isError,
  errorMessage,
  selectedDate,
  fallbackToday,
  onSelectDay,
  onPrev,
  onNext,
  onToday,
  onRetry,
}: {
  weekly: WeeklyData | undefined;
  isLoading: boolean;
  isError: boolean;
  errorMessage?: string;
  selectedDate: string | null;
  fallbackToday: string | null;
  onSelectDay: (date: string) => void;
  onPrev: () => void;
  onNext: () => void;
  onToday: () => void;
  onRetry: () => void;
}) {
  const today = weekly?.today ?? fallbackToday;
  const canNavigate = Boolean(selectedDate ?? today);

  return (
    <section className="card week-card" aria-label="Калории за неделю">
      <div className="week-heading">
        <div>
          <h2>Твоя неделя</h2>
          <p className="muted">
            {weekly
              ? formatWeekRange(weekly.start, weekly.end)
              : "Загрузка недели…"}
          </p>
        </div>
        <div className="week-controls">
          <button
            type="button"
            aria-label="Предыдущая неделя"
            onClick={onPrev}
            disabled={!canNavigate}
          >
            Предыдущая
          </button>
          <button type="button" onClick={onToday} disabled={!today}>
            Сегодня
          </button>
          <button
            type="button"
            aria-label="Следующая неделя"
            onClick={onNext}
            disabled={!canNavigate}
          >
            Следующая
          </button>
        </div>
      </div>

      {isError ? (
        <div className="inline-alert week-error" role="alert">
          <span>
            Не удалось загрузить неделю.{" "}
            {errorMessage ?? "Сервер недоступен."}
          </span>
          <button type="button" className="text-btn" onClick={onRetry}>
            Повторить
          </button>
        </div>
      ) : isLoading || !weekly ? (
        <div
          className="week-skeleton"
          aria-busy="true"
          aria-label="Загрузка недели"
        >
          {Array.from({ length: 7 }).map((_, index) => (
            <div key={index}>
              <Skeleton className="loading-line" style={{ width: 22, height: 10 }} />
              <Skeleton
                className="loading-line"
                style={{ width: 38, height: 38, borderRadius: "50%" }}
              />
              <Skeleton className="loading-line" style={{ width: 28, height: 10 }} />
              <Skeleton className="loading-line" style={{ width: 34, height: 8 }} />
            </div>
          ))}
        </div>
      ) : (
        <>
          <div className="week-rings">
            {weekly.days.map((day) => {
              const isSelected = day.date === selectedDate;
              const isToday = day.date === today;
              const dayNumber = String(Number(day.date.slice(8, 10)));
              const over = day.is_over_target;

              return (
                <button
                  key={day.date}
                  type="button"
                  onClick={() => onSelectDay(day.date)}
                  aria-pressed={isSelected}
                  aria-label={`${weekdayShort(day.date)} ${dayNumber}: ${
                    day.eaten === null
                      ? "нет записей"
                      : `съедено ${formatNumber(day.eaten)} ккал`
                  }${
                    day.target === null
                      ? ", цель не задана"
                      : `, цель ${formatNumber(day.target)} ккал`
                  }`}
                  className={cn(
                    "week-day",
                    isSelected && "selected",
                    isToday && "is-today",
                    over && "is-over",
                  )}
                >
                  <span className="day-name">{weekdayShort(day.date)}</span>
                  <DayRing
                    progress={day.progress}
                    over={over}
                    dayNumber={dayNumber}
                  />
                  <span className="day-cal">
                    {day.eaten === null ? "—" : formatNumber(day.eaten)}
                  </span>
                  <span className="day-target">
                    / {day.target === null ? "—" : formatNumber(day.target)}
                  </span>
                </button>
              );
            })}
          </div>

          <div className="week-legend">
            <span>
              <i aria-hidden="true" />
              Съедено / цель за день
            </span>
            <span>{formatNumber(weekly.eaten_total)} ккал за неделю</span>
          </div>
        </>
      )}
    </section>
  );
}
