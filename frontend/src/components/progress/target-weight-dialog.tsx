import { useState } from "react";
import { toast } from "sonner";

import { Modal } from "@/components/ui/modal";
import { useUpdateTargetWeight } from "@/hooks/use-weight";
import { ApiError, validationErrors } from "@/lib/api";
import { decimalParts } from "@/lib/entry-form";

export function TargetWeightDialog({
  target,
  onClose,
}: {
  target: number | null;
  onClose: () => void;
}) {
  const mutation = useUpdateTargetWeight();
  const [value, setValue] = useState(
    target === null ? "" : String(target),
  );
  const [error, setError] = useState<string | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);

  async function submit(override?: string): Promise<void> {
    setError(null);
    setSubmitError(null);
    const trimmed = override !== undefined ? override.trim() : value.trim();
    let nextValue: number | null = null;

    if (trimmed !== "") {
      const parsed = decimalParts(trimmed);

      if (
        parsed === null ||
        parsed.value < 35 ||
        parsed.value > 300 ||
        parsed.fractionDigits > 3
      ) {
        setError(
          "Целевой вес: от 35 до 300 кг, не более трёх знаков после запятой.",
        );
        return;
      }

      nextValue = parsed.value;
    }

    try {
      const saved = await mutation.mutateAsync(nextValue);
      toast.success(
        saved === null ? "Целевой вес убран" : "Целевой вес сохранён",
      );
      onClose();
    } catch (caught) {
      if (caught instanceof ApiError) {
        const fields = validationErrors(caught.payload);
        setError(fields.target_weight_kg ?? null);
        setSubmitError(
          fields.target_weight_kg ? null : caught.message,
        );
      } else {
        setSubmitError("Не удалось сохранить целевой вес.");
      }
    }
  }

  return (
    <Modal
      title="Целевой вес"
      describedBy="target-weight-note"
      onClose={() => {
        if (!mutation.isPending) {
          onClose();
        }
      }}
      closeDisabled={mutation.isPending}
    >
      <p id="target-weight-note" className="editor-subtitle">
        Необязательная личная настройка. Она рисуется пунктиром на графике и не
        меняет цель КБЖУ и расчёт питания.
      </p>
      <label>
        Целевой вес, кг
        <input
          type="text"
          inputMode="decimal"
          data-autofocus
          placeholder="Оставьте пустым, чтобы убрать"
          value={value}
          disabled={mutation.isPending}
          aria-invalid={Boolean(error)}
          onChange={(event) => setValue(event.target.value)}
        />
      </label>
      {error ? <p className="field-error">{error}</p> : null}
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
          disabled={mutation.isPending}
        >
          {mutation.isPending ? "Сохраняем…" : "Сохранить"}
        </button>
        {target !== null ? (
          <button
            type="button"
            className="text-btn"
            onClick={() => {
              setValue("");
              void submit("");
            }}
            disabled={mutation.isPending}
          >
            Убрать цель
          </button>
        ) : null}
        <button
          type="button"
          className="secondary"
          onClick={onClose}
          disabled={mutation.isPending}
        >
          Отмена
        </button>
      </div>
    </Modal>
  );
}
