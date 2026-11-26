import type { CSSProperties } from "react";

import { Skeleton } from "@/components/ui/skeleton";

import type { DashboardData } from "@/lib/dashboard-api";
import { formatNumber } from "@/lib/date";

export function CalorieBalanceCard({
  data,
  isLoading,
}: {
  data: DashboardData | undefined;
  isLoading: boolean;
}) {
  const target = data?.calories.target ?? null;
  const eaten = data?.calories.eaten ?? 0;
  const activity = data?.calories.activity ?? 0;
  const remaining = data?.calories.remaining ?? null;
  const excess = data?.calories.excess ?? null;
  const over = target !== null && excess !== null && excess > 0;
  const progress =
    target !== null && target > 0
      ? Math.min(100, (eaten / target) * 100)
      : 0;
  const ringStyle = {
    "--ring-progress": `${progress}%`,
  } as CSSProperties;

  const ringLabel =
    target === null
      ? "Цель дня ещё не задана"
      : over
        ? `Сверх цели на ${formatNumber(excess ?? 0)} ккал`
        : `Осталось ${formatNumber(remaining ?? 0)} ккал из ${formatNumber(target)}`;

  return (
    <section className="card balance" aria-labelledby="balance-title">
      <div className="label">
        <h2 id="balance-title">Баланс калорий</h2>
        <span style={{ color: "#bcdbca" }} aria-hidden="true">
          ◉
        </span>
      </div>

      {isLoading || !data ? (
        <div aria-busy="true" aria-label="Загрузка баланса">
          <Skeleton
            className="loading-line"
            style={{
              width: 178,
              height: 178,
              borderRadius: "50%",
              margin: "4px auto 22px",
              background: "#3b7863",
            }}
          />
          <div className="stats">
            {[0, 1, 2].map((item) => (
              <Skeleton
                key={item}
                className="loading-line"
                style={{ width: 62, height: 34, background: "#3b7863" }}
              />
            ))}
          </div>
        </div>
      ) : (
        <>
          <div className="calorie-ring" style={ringStyle} role="img" aria-label={ringLabel}>
            <div className="calorie-ring-inner">
              {target === null ? (
                <>
                  <strong className="ring-empty">—</strong>
                  <small>цель появится после анкеты</small>
                </>
              ) : over ? (
                <>
                  <small>Сверх цели</small>
                  <strong>{formatNumber(excess ?? 0)}</strong>
                  <small>ккал</small>
                </>
              ) : (
                <>
                  <small>Осталось</small>
                  <strong>{formatNumber(remaining ?? 0)}</strong>
                  <small>ккал на сегодня</small>
                </>
              )}
            </div>
          </div>

          <div className="stats">
            <div>
              <b>{formatNumber(eaten)}</b>
              <span>Съедено</span>
            </div>
            <div>
              <b>{target === null ? "—" : formatNumber(target)}</b>
              <span>Цель</span>
            </div>
            <div>
              <b>{formatNumber(activity)}</b>
              <span>Активность</span>
            </div>
          </div>
        </>
      )}
    </section>
  );
}
