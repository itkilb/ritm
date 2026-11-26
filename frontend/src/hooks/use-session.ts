import { useQuery } from "@tanstack/react-query";

import { ApiError, apiRequest } from "@/lib/api";
import type { ProfileInput } from "@/lib/goal-api";

export type SessionUser = {
  id: number | string;
  name?: string | null;
  email?: string | null;
  profile_completed: boolean;
  profile?: ProfileInput | null;
};

type SessionPayload =
  | SessionUser
  | {
      data?: SessionUser | null;
    };

// Only a 401 means "no session"; other failures are real errors for the guards.
export async function fetchSession(): Promise<SessionUser | null> {
  try {
    const payload = await apiRequest<SessionPayload>("/v1/me");

    const user =
      payload && typeof payload === "object" && "data" in payload
        ? payload.data ?? null
        : (payload as SessionUser | null);

    if (!user) {
      return null;
    }

    return {
      ...user,
      profile_completed: user.profile_completed === true,
    };
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null;
    }

    throw error;
  }
}

export function useSession() {
  return useQuery({
    queryKey: ["session"],
    queryFn: fetchSession,
    retry: false,
    staleTime: 30_000,
  });
}
