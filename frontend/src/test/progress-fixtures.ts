import type { ProgressData } from "@/lib/progress-api";
import { TEST_TODAY } from "@/test/fixtures";

export function progressFixture(
  overrides: Partial<ProgressData> = {},
): ProgressData {
  return {
    period: "month",
    start_date: "2025-01-01",
    end_date: TEST_TODAY,
    timezone: "Europe/Moscow",
    today: TEST_TODAY,
    weight: {
      entries: [
        { id: "weight-1", date: "2025-01-01", weight_kg: 80 },
        { id: "weight-2", date: "2025-01-15", weight_kg: 79.5 },
      ],
      average: [
        { date: "2025-01-15", weight_kg: 79.75, measurements: 2 },
      ],
      latest: { id: "weight-2", date: "2025-01-15", weight_kg: 79.5 },
      change_kg: -0.5,
      profile_weight_kg: 79.5,
      target_weight_kg: 75,
    },
    workouts: {
      totals: {
        count: 4,
        minutes: 120,
        duration_seconds: 7200,
        calories: 680,
        uncalculated_count: 0,
        distance_km: 11.5,
      },
      weeks: [
        {
          week_start: "2024-12-30",
          week_end: "2025-01-05",
          count: 0,
          minutes: 0,
          calories: 0,
          distance_km: 0,
        },
        {
          week_start: "2025-01-06",
          week_end: "2025-01-12",
          count: 2,
          minutes: 60,
          calories: 340,
          distance_km: 5,
        },
        {
          week_start: "2025-01-13",
          week_end: "2025-01-19",
          count: 2,
          minutes: 60,
          calories: 340,
          distance_km: 6.5,
        },
      ],
      by_type: { strength: 2, run: 1, cardio: 1 },
      run: {
        count: 1,
        minutes: 30,
        distance_km: 5,
        pace_seconds_per_km: 360,
        pace: "6:00",
      },
      cardio: {
        count: 1,
        minutes: 30,
        distance_km: 6.5,
        speed_kmh: null,
        kinds: [
          {
            activity_kind: "outdoor_bike",
            count: 1,
            minutes: 30,
            distance_km: 6.5,
            speed_kmh: 13,
          },
        ],
      },
    },
    exercise_history: null,
    ...overrides,
  };
}
