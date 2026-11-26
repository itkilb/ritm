import { describe, expect, it } from "vitest";

import { formatWeekRange, startOfIsoWeek } from "@/lib/date";

describe("week range formatting", () => {
  it("uses a genitive month inside one month", () => {
    expect(formatWeekRange("2025-01-20", "2025-01-26")).toBe(
      "20–26 января 2025",
    );
  });

  it("formats a range that crosses a month", () => {
    expect(formatWeekRange("2025-01-27", "2025-02-02")).toBe(
      "27 января — 2 февраля 2025",
    );
  });

  it("formats a range that crosses a year", () => {
    expect(formatWeekRange("2024-12-30", "2025-01-05")).toBe(
      "30 декабря 2024 — 5 января 2025",
    );
  });

  it("always starts a week on Monday", () => {
    expect(startOfIsoWeek("2025-01-19")).toBe("2025-01-13");
    expect(startOfIsoWeek("2025-01-20")).toBe("2025-01-20");
  });
});
