import { useMutation, useQueryClient } from "@tanstack/react-query";

import { ApiError } from "@/lib/api";
import { logoutUser } from "@/lib/auth-api";
import { useUiStore } from "@/stores/use-ui-store";

export function useLogout() {
  const queryClient = useQueryClient();

  return useMutation({
    // A 401 means the server session is already gone, so logout is complete.
    mutationFn: async () => {
      try {
        await logoutUser();
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          return;
        }

        throw error;
      }
    },
    onSuccess: () => {
      queryClient.clear();
      queryClient.setQueryData(["session"], null);
      useUiStore.getState().reset();
    },
  });
}
