import { describe, expect, it } from "vitest";

import {
  distanceFromSpeed,
  formatPace,
  roundTo,
  runningMetrics,
} from "@/lib/workout-math";

describe("workout math preview", () => {
  it("matches the server speed and pace rounding rules", () => {
    expect(runningMetrics(5.2, 1890)).toEqual({
      distance_km: 5.2,
      speed_kmh: 9.9,
      pace_seconds_per_km: 363,
      pace: "6:03",
    });

    expect(runningMetrics(4.95, 1800)).toEqual({
      distance_km: 4.95,
      speed_kmh: 9.9,
      pace_seconds_per_km: 364,
      pace: "6:04",
    });
  });

  it("derives distance from speed with three-decimal rounding", () => {
    expect(distanceFromSpeed(9.9, 1800)).toBe(4.95);
    expect(distanceFromSpeed(10, 601)).toBe(1.669);
    expect(distanceFromSpeed(20, 1800)).toBe(10);
  });

  it("never divides by zero or formats invalid pace", () => {
    expect(runningMetrics(0, 1800)).toBeNull();
    expect(runningMetrics(5, 0)).toBeNull();
    expect(runningMetrics(Number.NaN, 600)).toBeNull();
    expect(distanceFromSpeed(0, 600)).toBeNull();
    expect(distanceFromSpeed(10, 0)).toBeNull();
    expect(formatPace(null)).toBeNull();
    expect(formatPace(0)).toBeNull();
    expect(formatPace(3599.6)).toBe("60:00");
  });

  it("rounds half away from zero consistently", () => {
    expect(roundTo(1.005, 2)).toBe(1.01);
    expect(roundTo(1.004, 2)).toBe(1);
    expect(roundTo(1.2345, 3)).toBe(1.235);
  });
});
