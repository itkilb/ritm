import { useState } from "react";
import { toast } from "sonner";

import { Modal } from "@/components/ui/modal";
import { useCreateWeightEntry, useUpdateWeightEntry } from "@/hooks/use-weight";
import { ApiError, validationErrors } from "@/lib/api";
import { decimalParts } from "@/lib/entry-form";
import { parseIsoDate } from "@/lib/date";
import type { WeightPoint } from "@/lib/progress-api";

export function WeightEntryDialog({
  today,
  entry,
  onClose,
}: {
  today: string;
  entry: WeightPoint | null;
  onClose: () => void;
}) {
  const createMutation = useCreateWeightEntry();
  const updateMutation = useUpdateWeightEntry();
  const busy = createMutation.isPending || updateMutation.isPending;

  const [date, setDate] = useState(entry?.date ?? today);
  const [weight, setWeight] = useState(
    entry === null ? "" : String(entry.weight_kg),
  );
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  async function submit(): Promise<void> {
    setErrors({});
    setSubmitError(null);

    const nextErrors: Record<string, string> = {};

    if (!parseIsoDate(date)) {
      nextErrors.date = "Укажите действительную дату.";
    } else if (date > today) {
      nextErrors.date = "Нельзя записать вес на будущую дату.";
    }

    const parsed = decimalParts(weight);

    if (parsed === null) {
      nextErrors.weight_kg = "Введите вес числом.";
    } else if (
      parsed.value < 35 ||
      parsed.value > 300 ||
      parsed.fractionDigits > 3
    ) {
      nextErrors.weight_kg = "Вес: от 35 до 300 кг, не более трёх знаков после запятой.";
    }

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    try {
      if (entry) {
        await updateMutation.mutateAsync({
          id: entry.id,
          entry,
          date,
          weight_kg: parsed?.value ?? 0,
        });
        toast.success("Замер обновлён");
      } else {
        await createMutation.mutateAsync({ date, weight_kg: parsed?.value ?? 0 });
        toast.success("Замер добавлен");
      }

      onClose();
    } catch (error) {
      if (error instanceof ApiError) {
        const fields = validationErrors(error.payload);

        if (Object.keys(fields).length > 0) {
          setErrors(fields);
        } else {
          setSubmitError(error.message);
        }
      } else {
        setSubmitError("Не удалось сохранить замер. Попробуйте снова.");
      }
    }
  }

  return (
    <Modal
      title={entry ? "Изменить замер" : "Добавить замер"}
      describedBy="weight-dialog-note"
      onClose={() => {
        if (!busy) {
          onClose();
        }
      }}
      closeDisabled={busy}
    >
      <p id="weight-dialog-note" className="editor-subtitle">
        Один замер на календарный день. Дата и вес сохраняются на сервере.
      </p>
      <div className="field-grid">
        <label>
          Дата замера
          <input
            type="date"
            data-autofocus
            value={date}
            max={today}
            disabled={busy}
            aria-invalid={Boolean(errors.date)}
            onChange={(event) => setDate(event.target.value)}
          />
        </label>
        <label>
          Вес, кг
          <input
            type="text"
            inputMode="decimal"
            placeholder="Например, 72,4"
            value={weight}
            disabled={busy}
            aria-invalid={Boolean(errors.weight_kg)}
            onChange={(event) => setWeight(event.target.value)}
          />
        </label>
      </div>
      {errors.date ? <p className="field-error">{errors.date}</p> : null}
      {errors.weight_kg ? (
        <p className="field-error">{errors.weight_kg}</p>
      ) : null}
      {submitError ? (
        <p className="field-error" role="alert">
          {submitError}
        </p>
      ) : null}
      <div className="form-actions">
        <button
          type="button"
          className="primary"
          onClick={() => void submit()}
          disabled={busy}
        >
          {busy ? "Сохраняем…" : "Сохранить замер"}
        </button>
        <button
          type="button"
          className="secondary"
          onClick={onClose}
          disabled={busy}
        >
          Отмена
        </button>
      </div>
    </Modal>
  );
}
