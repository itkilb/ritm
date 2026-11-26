import { z } from "zod";

const DECIMAL_PATTERN = /^\d+(?:[.,]\d+)?$/;

export function decimalParts(
  value: string,
): { value: number; fractionDigits: number } | null {
  const normalized = value.trim().replace(",", ".");

  if (!DECIMAL_PATTERN.test(normalized)) {
    return null;
  }

  const [integerPart, fractionPart = ""] = normalized.split(".");
  const numeric = Number(`${integerPart}.${fractionPart || "0"}`);

  if (!Number.isFinite(numeric)) {
    return null;
  }

  return { value: numeric, fractionDigits: fractionPart.length };
}

export function decimalField(
  label: string,
  max: number,
  scale: number,
): z.ZodString {
  return z
    .string()
    .trim()
    .min(1, `Укажите: ${label.toLowerCase()}.`)
    .refine((value) => {
      const parts = decimalParts(value);

      return (
        parts !== null &&
        parts.value >= 0 &&
        parts.value <= max &&
        parts.fractionDigits <= scale
      );
    }, `${label}: число от 0 до ${max}, не более ${scale} знаков после запятой.`);
}

export function integerField(label: string, max: number): z.ZodString {
  return z
    .string()
    .trim()
    .min(1, `Укажите: ${label.toLowerCase()}.`)
    .refine(
      (value) => /^\d+$/.test(value) && Number(value) <= max,
      `${label}: целое число от 0 до ${max}.`,
    );
}

export function toDecimal(value: string): number {
  return decimalParts(value)?.value ?? 0;
}
