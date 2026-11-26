import { useQuery } from "@tanstack/react-query";

import { fetchCurrentGoal } from "@/lib/goal-api";

export function useCurrentGoal(enabled = true) {
  return useQuery({
    queryKey: ["goals", "current"],
    queryFn: fetchCurrentGoal,
    enabled,
    retry: false,
    staleTime: 30_000,
  });
}
