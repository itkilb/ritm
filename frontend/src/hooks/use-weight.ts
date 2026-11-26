import { useMutation, useQueryClient } from "@tanstack/react-query";

import { progressQueryPrefix } from "@/hooks/use-progress";
import {
  createWeightEntry,
  deleteWeightEntry,
  updateTargetWeight,
  updateWeightEntry,
  type WeightEntry,
} from "@/lib/weight-api";

function invalidateWeightCaches(
  queryClient: ReturnType<typeof useQueryClient>,
): void {
  void queryClient.invalidateQueries({ queryKey: progressQueryPrefix });
  void queryClient.invalidateQueries({
    queryKey: ["session"],
    refetchType: "none",
  });
  void queryClient.invalidateQueries({ queryKey: ["weight"] });
}

export function useCreateWeightEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: { date: string; weight_kg: number }) =>
      createWeightEntry(input),
    onSuccess: () => invalidateWeightCaches(queryClient),
  });
}

export function useUpdateWeightEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: {
      id: string;
      entry: WeightEntry;
      date: string;
      weight_kg: number;
    }) =>
      updateWeightEntry(variables.id, {
        date: variables.date,
        weight_kg: variables.weight_kg,
      }),
    onSuccess: () => invalidateWeightCaches(queryClient),
  });
}

export function useDeleteWeightEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: { id: string }) => deleteWeightEntry(variables.id),
    onSuccess: () => invalidateWeightCaches(queryClient),
  });
}

export function useUpdateTargetWeight() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (targetWeightKg: number | null) =>
      updateTargetWeight(targetWeightKg),
    onSuccess: () => invalidateWeightCaches(queryClient),
  });
}
