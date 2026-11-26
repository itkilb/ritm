import { apiRequest } from "@/lib/api";
import type { SessionUser } from "@/hooks/use-session";

export type AuthPayload = {
  data: SessionUser;
};

export type Credentials = {
  email: string;
  password: string;
};

export async function registerUser(values: Credentials): Promise<AuthPayload> {
  return apiRequest<AuthPayload>("/v1/auth/register", {
    method: "POST",
    body: JSON.stringify(values),
  });
}

export async function loginUser(values: Credentials): Promise<AuthPayload> {
  return apiRequest<AuthPayload>("/v1/auth/login", {
    method: "POST",
    body: JSON.stringify(values),
  });
}

export async function logoutUser(): Promise<void> {
  return apiRequest<void>("/v1/auth/logout", {
    method: "POST",
  });
}
