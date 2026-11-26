type DateParts = {
  year: number;
  month: number;
  day: number;
};

export function detectTimeZone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
  } catch {
    return "UTC";
  }
}

export function parseIsoDate(value: string): DateParts | null {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

  if (!match) {
    return null;
  }

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  // UTC normalizes 2025-02-30 to March; the round-trip check rejects it.
  const date = new Date(Date.UTC(year, month - 1, day));

  if (
    date.getUTCFullYear() !== year ||
    date.getUTCMonth() !== month - 1 ||
    date.getUTCDate() !== day
  ) {
    return null;
  }

  return { year, month, day };
}

export function todayInTimeZone(
  timeZone: string,
  now: Date = new Date(),
): DateParts | null {
  try {
    const formatter = new Intl.DateTimeFormat("en-US", {
      timeZone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
    });
    const parts = formatter.formatToParts(now);
    const year = Number(parts.find((part) => part.type === "year")?.value);
    const month = Number(parts.find((part) => part.type === "month")?.value);
    const day = Number(parts.find((part) => part.type === "day")?.value);

    if ([year, month, day].some((part) => !Number.isFinite(part))) {
      return null;
    }

    return { year, month, day };
  } catch {
    return null;
  }
}

export function calculateAge(
  birthDate: string,
  timeZone: string,
  now: Date = new Date(),
): number | null {
  const birth = parseIsoDate(birthDate);
  const today = todayInTimeZone(timeZone, now);

  if (!birth || !today) {
    return null;
  }

  let age = today.year - birth.year;

  if (
    today.month < birth.month ||
    (today.month === birth.month && today.day < birth.day)
  ) {
    age -= 1;
  }

  return age;
}

export function addIsoDays(value: string, days: number): string {
  const parts = parseIsoDate(value);

  if (!parts) {
    return value;
  }

  // UTC noon keeps date arithmetic independent of DST.
  const date = new Date(Date.UTC(parts.year, parts.month - 1, parts.day, 12));
  date.setUTCDate(date.getUTCDate() + days);

  return [
    String(date.getUTCFullYear()).padStart(4, "0"),
    String(date.getUTCMonth() + 1).padStart(2, "0"),
    String(date.getUTCDate()).padStart(2, "0"),
  ].join("-");
}

export function startOfIsoWeek(value: string): string {
  const parts = parseIsoDate(value);

  if (!parts) {
    return value;
  }

  const date = new Date(Date.UTC(parts.year, parts.month - 1, parts.day, 12));
  const isoWeekday = (date.getUTCDay() + 6) % 7; // Monday = 0, Sunday = 6

  return addIsoDays(value, -isoWeekday);
}

function isoUtcDate(value: string): Date | null {
  const parts = parseIsoDate(value);

  if (!parts) {
    return null;
  }

  return new Date(Date.UTC(parts.year, parts.month - 1, parts.day, 12));
}

export function formatIsoDate(
  value: string,
  options: Intl.DateTimeFormatOptions = {
    day: "numeric",
    month: "long",
    year: "numeric",
  },
): string {
  const date = isoUtcDate(value);

  if (!date) {
    return value;
  }

  return new Intl.DateTimeFormat("ru-RU", { ...options, timeZone: "UTC" }).format(
    date,
  );
}

export function weekdayShort(value: string): string {
  return formatIsoDate(value, { weekday: "short" });
}

const GENITIVE_MONTHS = [
  "января",
  "февраля",
  "марта",
  "апреля",
  "мая",
  "июня",
  "июля",
  "августа",
  "сентября",
  "октября",
  "ноября",
  "декабря",
] as const;

export function formatWeekRange(start: string, end: string): string {
  const startParts = parseIsoDate(start);
  const endParts = parseIsoDate(end);

  if (!startParts || !endParts) {
    return `${start} — ${end}`;
  }

  const startMonth = GENITIVE_MONTHS[startParts.month - 1];
  const endMonth = GENITIVE_MONTHS[endParts.month - 1];

  if (
    startParts.year === endParts.year &&
    startParts.month === endParts.month
  ) {
    return `${startParts.day}–${endParts.day} ${startMonth} ${startParts.year}`;
  }

  if (startParts.year === endParts.year) {
    return `${startParts.day} ${startMonth} — ${endParts.day} ${endMonth} ${startParts.year}`;
  }

  return `${startParts.day} ${startMonth} ${startParts.year} — ${endParts.day} ${endMonth} ${endParts.year}`;
}

export function formatNumber(value: number): string {
  return new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 2 }).format(
    value,
  );
}

export function formatDistance(value: number): string {
  return new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 3 }).format(
    value,
  );
}

export function formatWeight(value: number): string {
  return new Intl.NumberFormat("ru-RU", { maximumFractionDigits: 3 }).format(
    value,
  );
}
