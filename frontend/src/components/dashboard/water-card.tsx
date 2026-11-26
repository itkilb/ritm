import { toast } from "sonner";

import { useAddWater } from "@/hooks/use-dashboard";
import type { DashboardData } from "@/lib/dashboard-api";
import { formatNumber } from "@/lib/date";
import { cn } from "@/lib/utils";

const GLASS_ML = 250;
const GLASS_COUNT = 8;

function litres(amountMl: number): string {
  return formatNumber(amountMl / 1000);
}

export function WaterCard({
  data,
  isLoading,
  date,
}: {
  data: DashboardData | undefined;
  isLoading: boolean;
  date: string | null;
}) {
  const mutation = useAddWater();
  const amountMl = data?.water.amount_ml ?? 0;
  const targetMl = data?.water.target_ml ?? 2000;

  function handleAddGlass() {
    if (!date) {
      return;
    }

    // Capture the selected day now; an in-flight glass must not land on another date.
    mutation.mutate(
      { date, amountMl: GLASS_ML },
      {
        onSuccess: () => toast.success("Стакан добавлен"),
        onError: (error) =>
          toast.error(
            error instanceof Error
              ? error.message
              : "Не удалось добавить воду.",
          ),
      },
    );
  }

  return (
    <section className="card" aria-labelledby="water-title">
      <div className="water-head">
        <h2 id="water-title">Водный баланс</h2>
        <span className="water-glyph" aria-hidden="true">
          ♧
        </span>
      </div>

      {isLoading || !data ? (
        <div aria-busy="true" aria-label="Загрузка воды">
          <span
            className="loading-line"
            style={{ width: 130, height: 30, marginTop: 24 }}
          />
          <div className="cups">
            {Array.from({ length: GLASS_COUNT }).map((_, index) => (
              <span
                key={index}
                className="loading-line"
                style={{ height: 33, flex: 1, borderRadius: 6 }}
              />
            ))}
          </div>
        </div>
      ) : (
        <>
          <div className="water-number" aria-live="polite">
            <span>{litres(amountMl)}</span>{" "}
            <span className="muted">/ {litres(targetMl)} л</span>
          </div>

          <div className="cups" role="group" aria-label="Стаканы воды">
            {Array.from({ length: GLASS_COUNT }).map((_, index) => {
              const filled = amountMl >= (index + 1) * GLASS_ML;

              return (
                <span
                  key={index}
                  role="img"
                  aria-label={`Стакан ${index + 1}: ${
                    filled ? "заполнен" : "пуст"
                  }`}
                  className={cn("cup", filled && "filled")}
                />
              );
            })}
          </div>
        </>
      )}

      <div className="water-foot">
        <span className="muted">Один стакан — 250 мл</span>
        <button
          type="button"
          className="water-add"
          onClick={handleAddGlass}
          disabled={!date || mutation.isPending}
        >
          {mutation.isPending ? "Добавляем…" : "＋ Стакан"}
        </button>
      </div>
    </section>
  );
}
