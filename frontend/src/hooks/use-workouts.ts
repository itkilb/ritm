import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import {
  dashboardQueryKey,
  weeklyQueryKey,
} from "@/hooks/use-dashboard";
import { startOfIsoWeek } from "@/lib/date";
import {
  createWorkout,
  deleteWorkout,
  fetchWorkoutDiary,
  updateWorkout,
  type Workout,
  type WorkoutInput,
} from "@/lib/workout-api";

export const workoutQueryKey = (date: string | null) =>
  ["workouts", date ?? "today"] as const;

export const workoutQueryPrefix = ["workouts"] as const;

export function useWorkoutDiary(date: string | null) {
  return useQuery({
    queryKey: workoutQueryKey(date),
    queryFn: () => fetchWorkoutDiary(date),
    retry: false,
    staleTime: 15_000,
  });
}

export function invalidateWorkoutCaches(
  queryClient: ReturnType<typeof useQueryClient>,
  dates: Array<string | null | undefined>,
): void {
  // A workout can move between days/weeks; refresh the whole prefix and both dates.
  void queryClient.invalidateQueries({ queryKey: workoutQueryPrefix });
  void queryClient.invalidateQueries({ queryKey: ["progress"] });

  const uniqueDates = new Set<string>();

  for (const date of dates) {
    if (date) {
      uniqueDates.add(date);
      uniqueDates.add(startOfIsoWeek(date));
    }
  }

  for (const date of uniqueDates) {
    void queryClient.invalidateQueries({ queryKey: dashboardQueryKey(date) });
    void queryClient.invalidateQueries({
      queryKey: weeklyQueryKey(startOfIsoWeek(date)),
    });
  }

  void queryClient.invalidateQueries({ queryKey: dashboardQueryKey(null) });
  void queryClient.invalidateQueries({ queryKey: weeklyQueryKey(null) });
}

export function useCreateWorkout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: WorkoutInput) => createWorkout(input),
    onSuccess: (workout: Workout, input) => {
      invalidateWorkoutCaches(queryClient, [input.date, workout.date]);
    },
  });
}

export function useUpdateWorkout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: {
      id: string;
      input: WorkoutInput;
      previousDate: string;
    }) => updateWorkout(variables.id, variables.input),
    onSuccess: (workout: Workout, variables) => {
      invalidateWorkoutCaches(queryClient, [
        variables.previousDate,
        variables.input.date,
        workout.date,
      ]);
    },
  });
}

export function useDeleteWorkout() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (variables: { id: string; date: string }) =>
      deleteWorkout(variables.id),
    onSuccess: (_result, variables) => {
      invalidateWorkoutCaches(queryClient, [variables.date]);
    },
  });
}
