const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL ?? "").replace(/\/$/, "");

const SAFE_METHODS = new Set(["GET", "HEAD", "OPTIONS"]);

const NETWORK_ERROR_MESSAGE =
  "Не удалось связаться с сервером. Проверьте подключение и попробуйте снова.";

export class ApiError extends Error {
  readonly status: number;
  readonly payload: unknown;

  constructor(status: number, message: string, payload: unknown = null) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.payload = payload;
  }
}

function readCookie(name: string): string | null {
  const prefix = `${name}=`;
  const cookie = document.cookie
    .split(";")
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix));

  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : null;
}

async function readErrorPayload(response: Response): Promise<unknown> {
  const contentType = response.headers.get("content-type") ?? "";

  if (contentType.includes("application/json")) {
    try {
      return await response.json();
    } catch {
      return null;
    }
  }

  return response.text().catch(() => null);
}

function payloadMessage(payload: unknown): string | null {
  if (
    payload !== null &&
    typeof payload === "object" &&
    "message" in payload &&
    typeof payload.message === "string"
  ) {
    return payload.message;
  }

  return null;
}

function errorMessage(status: number, payload: unknown): string {
  if (status === 419) {
    return "Сессия истекла. Обновите страницу и попробуйте снова.";
  }

  if (status === 429) {
    return payloadMessage(payload) ?? "Слишком много попыток. Попробуйте позже.";
  }

  if (status === 401) {
    return payloadMessage(payload) ?? "Требуется вход.";
  }

  return payloadMessage(payload) ?? `Запрос завершился с ошибкой ${status}`;
}

export function validationErrors(payload: unknown): Record<string, string> {
  if (
    payload === null ||
    typeof payload !== "object" ||
    !("errors" in payload) ||
    payload.errors === null ||
    typeof payload.errors !== "object"
  ) {
    return {};
  }

  const result: Record<string, string> = {};

  for (const [field, messages] of Object.entries(payload.errors)) {
    if (typeof messages === "string") {
      result[field] = messages;
    } else if (Array.isArray(messages) && typeof messages[0] === "string") {
      result[field] = messages[0];
    }
  }

  return result;
}

let csrfRequest: Promise<void> | null = null;

// The XSRF cookie is JS-readable; the session cookie remains HttpOnly.
export async function ensureCsrfCookie(force = false): Promise<void> {
  if (!force && readCookie("XSRF-TOKEN")) {
    return;
  }

  if (csrfRequest === null) {
    csrfRequest = fetch(`${API_BASE_URL}/sanctum/csrf-cookie`, {
      credentials: "include",
      headers: { Accept: "application/json" },
    })
      .then((response) => {
        if (!response.ok) {
          throw new ApiError(
            response.status,
            "Не удалось подготовить защищённую сессию.",
          );
        }
      })
      .catch((error: unknown) => {
        if (error instanceof ApiError) {
          throw error;
        }

        throw new ApiError(0, NETWORK_ERROR_MESSAGE);
      })
      .finally(() => {
        csrfRequest = null;
      });
  }

  await csrfRequest;
}

async function sendRequest(path: string, init: RequestInit): Promise<Response> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  if (init.body !== undefined && !(init.body instanceof FormData) && !headers.has("Content-Type")) {
    headers.set("Content-Type", "application/json");
  }

  const xsrfToken = readCookie("XSRF-TOKEN");
  if (xsrfToken && !headers.has("X-XSRF-TOKEN")) {
    headers.set("X-XSRF-TOKEN", xsrfToken);
  }

  try {
    return await fetch(`${API_BASE_URL}${path}`, {
      ...init,
      credentials: "include",
      headers,
    });
  } catch (error) {
    if (error instanceof DOMException && error.name === "AbortError") {
      throw error;
    }

    throw new ApiError(0, NETWORK_ERROR_MESSAGE);
  }
}

export async function apiRequest<T>(
  path: string,
  init: RequestInit = {},
): Promise<T> {
  const method = (init.method ?? "GET").toUpperCase();
  const isUnsafe = !SAFE_METHODS.has(method);

  if (isUnsafe) {
    await ensureCsrfCookie();
  }

  let response = await sendRequest(path, init);

  if (isUnsafe && response.status === 419) {
    await ensureCsrfCookie(true);
    response = await sendRequest(path, init);
  }

  if (!response.ok) {
    const payload = await readErrorPayload(response);
    throw new ApiError(response.status, errorMessage(response.status, payload), payload);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}
