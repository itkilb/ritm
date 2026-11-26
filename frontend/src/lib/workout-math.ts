export type RunningMetrics = {
  distance_km: number;
  speed_kmh: number;
  pace_seconds_per_km: number;
  pace: string;
};

export function roundTo(value: number, precision: number): number {
  const factor = 10 ** precision;
  const sign = value < 0 ? -1 : 1;
  const scaled = Math.abs(value) * factor;

  // Nudge binary float noise so the result matches PHP's half-away-from-zero rounding.
  return (
    (sign * Math.round(scaled + Number.EPSILON * Math.max(1, scaled))) /
    factor
  );
}

export function formatPace(paceSecondsPerKm: number | null): string | null {
  if (
    paceSecondsPerKm === null ||
    !Number.isFinite(paceSecondsPerKm) ||
    paceSecondsPerKm <= 0
  ) {
    return null;
  }

  const total = Math.round(paceSecondsPerKm);

  return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, "0")}`;
}

export function distanceFromSpeed(
  speedKmh: number,
  durationSeconds: number,
): number | null {
  if (
    !Number.isFinite(speedKmh) ||
    !Number.isFinite(durationSeconds) ||
    speedKmh <= 0 ||
    durationSeconds <= 0
  ) {
    return null;
  }

  return roundTo((speedKmh * durationSeconds) / 3600, 3);
}

export function runningMetrics(
  distanceKm: number,
  durationSeconds: number,
): RunningMetrics | null {
  if (
    !Number.isFinite(distanceKm) ||
    !Number.isFinite(durationSeconds) ||
    distanceKm <= 0 ||
    durationSeconds <= 0
  ) {
    return null;
  }

  const paceSeconds = Math.round(durationSeconds / distanceKm);

  return {
    distance_km: distanceKm,
    speed_kmh: roundTo(distanceKm / (durationSeconds / 3600), 2),
    pace_seconds_per_km: paceSeconds,
    pace: formatPace(paceSeconds) ?? "—",
  };
}
