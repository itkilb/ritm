import { useQuery } from "@tanstack/react-query";

import { fetchProgress, type ProgressPeriod } from "@/lib/progress-api";

export const progressQueryPrefix = ["progress"] as const;

export const progressQueryKey = (
  period: ProgressPeriod,
  date: string | null,
  exercise: string | null,
) => ["progress", period, date ?? "today", exercise ?? null] as const;

export function useProgress(
  period: ProgressPeriod,
  date: string | null,
  exercise: string | null,
) {
  return useQuery({
    queryKey: progressQueryKey(period, date, exercise),
    queryFn: () => fetchProgress(period, date, exercise),
    retry: false,
    staleTime: 15_000,
  });
}
