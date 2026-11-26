import { Skeleton } from "@/components/ui/skeleton";
import type { WorkoutDiary } from "@/lib/workout-api";
import { formatWeekRange, weekdayShort } from "@/lib/date";
import { cn } from "@/lib/utils";

export function WorkoutWeekCard({
  diary,
  selectedDate,
  onSelectDay,
  onPrev,
  onNext,
  onToday,
}: {
  diary: WorkoutDiary | undefined;
  selectedDate: string | null;
  onSelectDay: (date: string) => void;
  onPrev: () => void;
  onNext: () => void;
  onToday: () => void;
}) {
  const canNavigate = Boolean(selectedDate ?? diary?.today);

  return (
    <section className="card" aria-label="Тренировочная неделя">
      <div className="training-week-heading">
        <div>
          <h2>Твоя тренировочная неделя</h2>
          <p className="training-small-muted">
            {diary
              ? formatWeekRange(diary.week_start, diary.week_end)
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
          <button type="button" onClick={onToday} disabled={!diary?.today}>
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

      {diary ? (
        <div className="training-week">
          {diary.days.map((day) => {
            const isSelected = day.date === selectedDate;
            const isToday = day.date === diary.today;
            const dayNumber = String(Number(day.date.slice(8, 10)));

            return (
              <button
                key={day.date}
                type="button"
                className={cn(
                  "training-day",
                  isSelected && "selected",
                  isToday && "is-today",
                )}
                aria-pressed={isSelected}
                aria-label={`${weekdayShort(day.date)} ${dayNumber}: ${
                  day.count === 0
                    ? "нет тренировок"
                    : `${day.count} тренировок`
                }`}
                onClick={() => onSelectDay(day.date)}
              >
                <span>{weekdayShort(day.date)}</span>
                <b>{dayNumber}</b>
                <i className={day.has_workouts ? "logged" : undefined} aria-hidden="true" />
              </button>
            );
          })}
        </div>
      ) : (
        <div
          className="training-week"
          aria-busy="true"
          aria-label="Загрузка тренировочной недели"
        >
          {Array.from({ length: 7 }).map((_, index) => (
            <div key={index} className="training-day-skeleton">
              <Skeleton className="loading-line" style={{ width: 26, height: 10 }} />
              <Skeleton
                className="loading-line"
                style={{ width: 30, height: 22, marginTop: 8 }}
              />
            </div>
          ))}
        </div>
      )}
    </section>
  );
}
