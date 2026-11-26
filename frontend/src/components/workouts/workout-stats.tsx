import type { WorkoutWeekTotals } from "@/lib/workout-api";
import { formatDistance, formatNumber } from "@/lib/date";

export function WorkoutStats({ week }: { week: WorkoutWeekTotals | undefined }) {
  return (
    <div className="training-overview" aria-label="Итоги тренировочной недели">
      <div className="training-stat primary-stat">
        <span className="stat-icon">За неделю</span>
        <strong className="stat-number">
          {week ? formatNumber(week.count) : "—"}
        </strong>
        <span className="stat-label">тренировок</span>
      </div>
      <div className="training-stat">
        <span className="stat-icon">В движении</span>
        <strong className="stat-number">
          {week ? new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 1 }).format(week.minutes) : "—"}
        </strong>
        <span className="stat-label">минут за неделю</span>
      </div>
      <div className="training-stat">
        <span className="stat-icon">Дистанция</span>
        <strong className="stat-number">
          {week ? formatDistance(week.distance_km) : "—"}
        </strong>
        <span className="stat-label">км бега за неделю</span>
      </div>
      {week && week.uncalculated_count > 0 ? (
        <p className="training-uncalculated">
          Без расчёта калорий: {formatNumber(week.uncalculated_count)}
        </p>
      ) : null}
    </div>
  );
}
