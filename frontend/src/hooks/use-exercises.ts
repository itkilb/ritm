import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import {
  createExercise,
  fetchExercises,
  type Exercise,
  type ExerciseInput,
} from "@/lib/exercise-api";

export const exerciseQueryKey = ["exercises"] as const;

export function useExercises(enabled = true) {
  return useQuery({
    queryKey: exerciseQueryKey,
    queryFn: fetchExercises,
    enabled,
    staleTime: 5 * 60_000,
    retry: false,
  });
}

export function useCreateExercise() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (input: ExerciseInput) => createExercise(input),
    onSuccess: (exercise: Exercise) => {
      queryClient.setQueryData<{ data: Exercise[] } | undefined>(
        exerciseQueryKey,
        (current) =>
          current
            ? { ...current, data: [...current.data, exercise] }
            : current,
      );
      void queryClient.invalidateQueries({ queryKey: exerciseQueryKey });
    },
  });
}
