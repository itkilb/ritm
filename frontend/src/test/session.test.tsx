import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { fetchSession } from "@/hooks/use-session";
import { ApiError, apiRequest } from "@/lib/api";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/api")>();

  return {
    ...actual,
    apiRequest: vi.fn(),
  };
});

const completedUser = {
  id: 2,
  email: "completed@example.com",
  name: null,
  profile_completed: true,
};

describe("session check", () => {
  it("treats only 401 as a guest", async () => {
    vi.mocked(apiRequest).mockRejectedValueOnce(
      new ApiError(401, "Требуется вход."),
    );

    await expect(fetchSession()).resolves.toBeNull();
  });

  it("surfaces 404 instead of silently treating it as a guest", async () => {
    vi.mocked(apiRequest).mockRejectedValueOnce(
      new ApiError(404, "Страница не найдена."),
    );

    await expect(fetchSession()).rejects.toMatchObject({ status: 404 });
  });

  it("shows a session-check error with retry for a 404 response", async () => {
    vi.mocked(apiRequest)
      .mockRejectedValueOnce(new ApiError(404, "Страница не найдена."))
      .mockResolvedValueOnce({ data: completedUser });

    const user = userEvent.setup();
    renderApp({ user: null, route: "/dashboard", seedSession: false });

    expect(
      await screen.findByText("Не удалось проверить сессию"),
    ).toBeInTheDocument();
    expect(screen.getByText("Страница не найдена.")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Повторить" }));

    expect(
      await screen.findByRole("heading", { name: /Хороший день для себя/ }),
    ).toBeInTheDocument();
  });
});
