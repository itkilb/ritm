import { afterEach, describe, expect, it, vi } from "vitest";

import { ApiError, apiRequest, ensureCsrfCookie } from "@/lib/api";

const NETWORK_ERROR_MESSAGE =
  "Не удалось связаться с сервером. Проверьте подключение и попробуйте снова.";

async function captureError(action: () => Promise<unknown>): Promise<unknown> {
  try {
    await action();
  } catch (error) {
    return error;
  }

  throw new Error("Ожидалась ошибка, но promise разрешился.");
}

function clearXsrfCookie() {
  document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
}

afterEach(() => {
  vi.unstubAllGlobals();
  clearXsrfCookie();
});

describe("API client", () => {
  it("normalizes a CSRF bootstrap network failure like a regular request", async () => {
    clearXsrfCookie();
    vi.stubGlobal(
      "fetch",
      vi.fn().mockRejectedValue(new TypeError("Failed to fetch")),
    );

    const bootstrapError = await captureError(() => ensureCsrfCookie(true));
    const requestError = await captureError(() => apiRequest("/v1/me"));

    expect(bootstrapError).toBeInstanceOf(ApiError);
    expect(bootstrapError).toMatchObject({
      status: 0,
      message: NETWORK_ERROR_MESSAGE,
    });
    expect(requestError).toMatchObject({
      status: 0,
      message: NETWORK_ERROR_MESSAGE,
    });
    expect((bootstrapError as ApiError).message).toBe(
      (requestError as ApiError).message,
    );
  });

  it("preserves the HTTP status of a failed CSRF bootstrap", async () => {
    clearXsrfCookie();
    vi.stubGlobal(
      "fetch",
      vi.fn().mockResolvedValue(new Response(null, { status: 503 })),
    );

    const error = await captureError(() => ensureCsrfCookie(true));

    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({
      status: 503,
      message: "Не удалось подготовить защищённую сессию.",
    });
  });
});
