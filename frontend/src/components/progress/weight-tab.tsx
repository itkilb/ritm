import { useState } from "react";
import { toast } from "sonner";

import { TargetWeightDialog } from "@/components/progress/target-weight-dialog";
import { WeightChart } from "@/components/progress/weight-chart";
import { WeightEntryDialog } from "@/components/progress/weight-entry-dialog";
import { useDeleteWeightEntry } from "@/hooks/use-weight";
import { formatWeight } from "@/lib/date";
import type { ProgressWeight } from "@/lib/progress-api";

function changeLabel(change: number | null): string {
  if (change === null) {
    return "—";
  }

  if (change === 0) {
    return "0 кг";
  }

  return `${change > 0 ? "+" : "−"}${formatWeight(Math.abs(change))} кг`;
}

export function WeightTab({
  weight,
  today,
}: {
  weight: ProgressWeight;
  today: string;
}) {
  const deleteMutation = useDeleteWeightEntry();
  const [dialog, setDialog] = useState<
    { mode: "create" } | { mode: "edit"; entry: ProgressWeight["entries"][number] } | null
  >(null);
  const [targetOpen, setTargetOpen] = useState(false);

  function remove(id: string): void {
    deleteMutation.mutate(
      { id },
      {
        onSuccess: () => toast.success("Замер удалён"),
        onError: (error) =>
          toast.error(
            error instanceof Error ? error.message : "Не удалось удалить замер.",
          ),
      },
    );
  }

  const latest = weight.latest;

  return (
    <section className="progress-tab" aria-label="Вес">
      <div className="progress-cards">
        <article className="progress-card">
          <span>Текущий вес</span>
          <strong>
            {latest === null ? "—" : `${formatWeight(latest.weight_kg)} кг`}
          </strong>
          <small>
            {latest === null
              ? "Нет фактических замеров"
              : `${latest.date} · фактический замер`}
          </small>
        </article>
        <article className="progress-card">
          <span>Изменение за период</span>
          <strong>{changeLabel(weight.change_kg)}</strong>
          <small>
            {weight.entries.length < 2
              ? "Нужно минимум два замера в периоде"
              : "Между первым и последним замером периода"}
          </small>
        </article>
        <article className="progress-card target-card">
          <span>Целевой вес</span>
          <strong>
            {weight.target_weight_kg === null
              ? "Не задан"
              : `${formatWeight(weight.target_weight_kg)} кг`}
          </strong>
          <button
            type="button"
            className="text-btn"
            onClick={() => setTargetOpen(true)}
          >
            {weight.target_weight_kg === null ? "Задать цель" : "Изменить цель"}
          </button>
        </article>
      </div>

      <div className="progress-card progress-chart-card">
        <div className="progress-section-head">
          <div>
            <h2>Динамика веса</h2>
            <p>Реальные замеры, среднее за 7 дней и целевой ориентир.</p>
          </div>
          <button
            type="button"
            className="primary"
            onClick={() => setDialog({ mode: "create" })}
          >
            ＋ Добавить замер
          </button>
        </div>
        <WeightChart
          entries={weight.entries}
          average={weight.average}
          target={weight.target_weight_kg}
        />
      </div>

      <div className="progress-card">
        <div className="progress-section-head">
          <div>
            <h2>Замеры</h2>
            <p>
              Дата и вес. Один замер на день; дубликат отклоняется сервером.
            </p>
          </div>
        </div>
        {weight.entries.length === 0 ? (
          <div className="progress-empty">
            <b>Журнал веса пока пуст</b>
            <p>
              Добавьте первый замер. Если журнал пуст, в профиле остаётся
              последний сохранённый вес
              {weight.profile_weight_kg === null
                ? ""
                : ` (${formatWeight(weight.profile_weight_kg)} кг)`}
              ; он не показывается как исторический замер.
            </p>
            <button
              type="button"
              className="secondary"
              onClick={() => setDialog({ mode: "create" })}
            >
              Добавить первый замер
            </button>
          </div>
        ) : (
          <ul className="weight-list" aria-label="Список замеров веса">
            {weight.entries.map((entry) => (
              <li key={entry.id}>
                <span className="weight-date">{entry.date}</span>
                <b>{formatWeight(entry.weight_kg)} кг</b>
                <span className="weight-actions">
                  <button
                    type="button"
                    className="text-btn"
                    aria-label={`Изменить замер ${entry.date}`}
                    onClick={() => setDialog({ mode: "edit", entry })}
                    disabled={deleteMutation.isPending}
                  >
                    Изменить
                  </button>
                  <button
                    type="button"
                    className="text-btn danger"
                    aria-label={`Удалить замер ${entry.date}`}
                    onClick={() => remove(entry.id)}
                    disabled={deleteMutation.isPending}
                  >
                    {deleteMutation.isPending ? "Удаляем…" : "Удалить"}
                  </button>
                </span>
              </li>
            ))}
          </ul>
        )}
      </div>

      {dialog ? (
        <WeightEntryDialog
          key={dialog.mode === "edit" ? dialog.entry.id : `new-${today}`}
          today={today}
          entry={dialog.mode === "edit" ? dialog.entry : null}
          onClose={() => setDialog(null)}
        />
      ) : null}

      {targetOpen ? (
        <TargetWeightDialog
          target={weight.target_weight_kg}
          onClose={() => setTargetOpen(false)}
        />
      ) : null}
    </section>
  );
}
