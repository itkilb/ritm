import { Skeleton } from "@/components/ui/skeleton";
import type { DashboardData } from "@/lib/dashboard-api";
import { formatDistance, formatNumber } from "@/lib/date";

export function ActivityCard({
  data,
  isLoading,
  onAddActivity,
  onOpenWorkouts,
}: {
  data: DashboardData | undefined;
  isLoading: boolean;
  onAddActivity: () => void;
  onOpenWorkouts: () => void;
}) {
  const steps = data?.steps;

  return (
    <section className="card activity" id="activity" aria-labelledby="activity-title">
      <div className="label">
        <h2 id="activity-title">Активность</h2>
        <div className="activity-icon" aria-hidden="true">
          ♧
        </div>
      </div>

      {isLoading || !steps ? (
        <div aria-busy="true" aria-label="Загрузка активности">
          <Skeleton className="loading-line" style={{ width: "70%", height: 36 }} />
          <Skeleton
            className="loading-line"
            style={{ width: "55%", height: 14, marginTop: 8 }}
          />
          <div className="activity-summary">
            <Skeleton className="loading-line" style={{ width: 74, height: 38 }} />
            <Skeleton className="loading-line" style={{ width: 74, height: 38 }} />
          </div>
        </div>
      ) : (
        <>
          <div className="steps">
            {formatNumber(steps.total)} <span>шагов</span>
          </div>
          <div className="muted" style={{ marginTop: 4 }}>
            из {formatNumber(steps.goal)} · осталось{" "}
            {formatNumber(steps.remaining)}
          </div>
          <div className="activity-summary">
            <div>
              <b>{formatNumber(steps.minutes)}</b>
              <span>минут движения</span>
            </div>
            <div>
              <b>{formatDistance(steps.distance_km)}</b>
              <span>км за день</span>
            </div>
          </div>
        </>
      )}

      <button
        type="button"
        className="add-meal"
        onClick={onAddActivity}
        disabled={!data}
      >
        ＋ Добавить активность
      </button>
      <button
        type="button"
        className="text-btn training-link"
        onClick={onOpenWorkouts}
      >
        Дневник тренировок
      </button>
      <div className="profile-summary" aria-live="polite">
        {data && data.activity.workout_totals.count > 0
          ? `Тренировок за день: ${formatNumber(data.activity.workout_totals.count)}`
          : "Тренировки ещё не записаны"}
        {data && (data.activity.workout_totals.uncalculated_count ?? 0) > 0
          ? ` · без расчёта калорий: ${formatNumber(
              data.activity.workout_totals.uncalculated_count ?? 0,
            )}`
          : ""}
      </div>
    </section>
  );
}
