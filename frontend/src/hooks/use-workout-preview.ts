import { useCallback, useEffect, useRef, useState } from "react";

import {
  previewWorkout,
  type WorkoutCaloriePreview,
  type WorkoutCaloriePreviewInput,
} from "@/lib/workout-api";

type PreviewState = {
  data: WorkoutCaloriePreview | null;
  error: Error | null;
  isLoading: boolean;
};

function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

export function useWorkoutPreview({
  input,
  workoutId,
  enabled,
  sessionId,
  debounceMs = 350,
}: {
  input: WorkoutCaloriePreviewInput | null;
  workoutId?: string | null;
  enabled: boolean;
  sessionId: string | number | null;
  debounceMs?: number;
}) {
  const [state, setState] = useState<PreviewState>({
    data: null,
    error: null,
    isLoading: false,
  });
  const [revision, setRevision] = useState(0);
  const sequence = useRef(0);
  const immediate = useRef(false);

  const serialized = input ? JSON.stringify(input) : null;

  useEffect(() => {
    if (!enabled || serialized === null) {
      ++sequence.current;
      setState({ data: null, error: null, isLoading: false });

      return;
    }

    // Only the latest request for the current session/date may update state.
    const requestId = ++sequence.current;
    const controller = new AbortController();
    const delay = immediate.current ? 0 : debounceMs;
    immediate.current = false;

    setState((current) => ({ ...current, error: null, isLoading: true }));

    const timer = window.setTimeout(() => {
      let parsed: WorkoutCaloriePreviewInput;

      try {
        parsed = JSON.parse(serialized) as WorkoutCaloriePreviewInput;
      } catch {
        setState({
          data: null,
          error: new Error("Не удалось собрать данные для расчёта."),
          isLoading: false,
        });

        return;
      }

      void previewWorkout(parsed, workoutId ?? null, controller.signal)
        .then((data) => {
          if (requestId !== sequence.current || controller.signal.aborted) {
            return;
          }

          setState({ data, error: null, isLoading: false });
        })
        .catch((error: unknown) => {
          if (
            requestId !== sequence.current ||
            controller.signal.aborted ||
            isAbortError(error)
          ) {
            return;
          }

          setState({
            data: null,
            error:
              error instanceof Error
                ? error
                : new Error("Не удалось получить расчёт калорий."),
            isLoading: false,
          });
        });
    }, delay);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [
    serialized,
    workoutId,
    enabled,
    sessionId,
    revision,
    debounceMs,
  ]);

  const reload = useCallback(() => {
    immediate.current = true;
    setRevision((value) => value + 1);
  }, []);

  return { ...state, reload };
}
