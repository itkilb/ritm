import { Skeleton } from "@/components/ui/skeleton";
import type { DashboardData, MacroSummary } from "@/lib/dashboard-api";
import { formatNumber } from "@/lib/date";

const MACROS = [
  { key: "protein", label: "Белки", color: "#8cae91" },
  { key: "fat", label: "Жиры", color: "#e9bc73" },
  { key: "carbs", label: "Углеводы", color: "#a69bc3" },
] as const;

function progressWidth(macro: MacroSummary): number {
  if (macro.target === null || macro.target <= 0) {
    return 0;
  }

  return Math.max(0, Math.min(100, (macro.consumed / macro.target) * 100));
}

export function MacrosCard({
  data,
  isLoading,
  onEditGoal,
}: {
  data: DashboardData | undefined;
  isLoading: boolean;
  onEditGoal: () => void;
}) {
  const target = data?.target ?? null;
  const hint = target
    ? `${
        target.goal === "lose_weight"
          ? "Похудение · дефицит 15%"
          : "Набор массы · профицит 10%"
      }. Норма уже учитывает выбранный уровень активности.`
    : "Норма появится после подтверждения анкеты. Данные за день при этом сохраняются.";

  return (
    <section className="card" aria-labelledby="macros-title">
      <div className="label">
        <h2 id="macros-title">Твои КБЖУ</h2>
        <button type="button" className="chip" onClick={onEditGoal}>
          Моя цель
        </button>
      </div>

      {isLoading || !data ? (
        <div aria-busy="true" aria-label="Загрузка КБЖУ">
          {MACROS.map((macro) => (
            <div className="macro" key={macro.key}>
              <Skeleton className="loading-line" style={{ width: "100%", height: 16 }} />
              <Skeleton
                className="loading-line"
                style={{ width: "100%", height: 7, marginTop: 10 }}
              />
            </div>
          ))}
        </div>
      ) : (
        <>
          {MACROS.map((macro) => {
            const value = data.macros[macro.key];
            const width = progressWidth(value);
            const targetLabel =
              value.target === null ? "—" : formatNumber(value.target);

            return (
              <div className="macro" key={macro.key}>
                <div className="macro-head">
                  <b>{macro.label}</b>
                  <span>
                    <b>{formatNumber(value.consumed)}</b>{" "}
                    <span className="muted">/ {targetLabel} г</span>
                  </span>
                </div>
                <div
                  className="track"
                  role="progressbar"
                  aria-label={`${macro.label}: ${formatNumber(
                    value.consumed,
                  )} из ${
                    value.target === null
                      ? "нет нормы"
                      : formatNumber(value.target)
                  } граммов`}
                  aria-valuemin={0}
                  aria-valuemax={100}
                  aria-valuenow={Math.round(width)}
                >
                  <i
                    style={{
                      width: `${width}%`,
                      backgroundColor: macro.color,
                    }}
                  />
                </div>
              </div>
            );
          })}

          <p className="hint">{hint}</p>
        </>
      )}
    </section>
  );
}
