import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import {
  addWater,
  changeFoodEntryMealType,
  createActivityEntry,
  createFoodEntry,
  deleteFoodEntry,
  fetchDashboard,
  fetchWeeklyStats,
  type ActivityEntryInput,
  type FoodEntryInput,
  type MealType,
} from "@/lib/dashboard-api";
import { startOfIsoWeek } from "@/lib/date";

export const dashboardQueryKey = (date: string | null) =>
  ["dashboard", date ?? "today"] as const;

export const weeklyQueryKey = (start: string | null) =>
  ["weekly", start ?? "current"] as const;

export function useDashboard(date: string | null) {
  return useQuery({
    queryKey: dashboardQueryKey(date),
    queryFn: () => fetchDashboard(date),
    retry: false,
    staleTime: 15_000,
  });
}

export function useWeeklyStats(start: string | null) {
  return useQuery({
    queryKey: weeklyQueryKey(start),
    queryFn: () => fetchWeeklyStats(start),
    retry: false,
    staleTime: 15_000,
  });
}

// Invalidate only the captured day and its ISO week, never the currently visible date.
function invalidateDay(queryClient: ReturnType<typeof useQueryClient>, date: string): void {
  void queryClient.invalidateQueries({ queryKey: dashboardQueryKey(date) });
  void queryClient.invalidateQueries({ queryKey: dashboardQueryKey(null) });
  void queryClient.invalidateQueries({ queryKey: weeklyQueryKey(startOfIsoWeek(date)) });
  void queryClient.invalidateQueries({ queryKey: weeklyQueryKey(null) });
}

export function useCreateFoodEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: FoodEntryInput) => createFoodEntry(input),
    onSuccess: (entry) => invalidateDay(queryClient, entry.date),
  });
}

export function useChangeFoodMealType() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: {
      id: string;
      mealType: MealType;
      date: string;
    }) => changeFoodEntryMealType(variables.id, variables.mealType),
    onSuccess: (_entry, variables) =>
      invalidateDay(queryClient, variables.date),
  });
}

export function useDeleteFoodEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: { id: string; date: string }) =>
      deleteFoodEntry(variables.id),
    onSuccess: (_result, variables) => invalidateDay(queryClient, variables.date),
  });
}

export function useCreateActivityEntry() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: ActivityEntryInput) => createActivityEntry(input),
    onSuccess: (entry) => invalidateDay(queryClient, entry.date),
  });
}

export function useAddWater() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: { date: string; amountMl?: number }) =>
      addWater(variables.date, variables.amountMl ?? 250),
    onSuccess: (state) => invalidateDay(queryClient, state.date),
  });
}
