import { screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import { ApiError } from "@/lib/api";
import { logoutUser } from "@/lib/auth-api";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/auth-api", () => ({
  registerUser: vi.fn(),
  loginUser: vi.fn(),
  logoutUser: vi.fn().mockResolvedValue(undefined),
}));

const incompleteUser: SessionUser = {
  id: 1,
  email: "incomplete@example.com",
  name: null,
  profile_completed: false,
};

const completedUser: SessionUser = {
  id: 2,
  email: "completed@example.com",
  name: null,
  profile_completed: true,
};

describe("auth routing", () => {
  it("sends a guest from /dashboard to the login screen", async () => {
    renderApp({ user: null, route: "/dashboard" });

    expect(
      await screen.findByRole("button", { name: "Войти" }),
    ).toBeInTheDocument();
  });

  it("sends an authenticated user with an incomplete profile to onboarding", async () => {
    renderApp({ user: incompleteUser, route: "/dashboard" });

    expect(await screen.findByText("Настроим твой баланс")).toBeInTheDocument();
  });

  it("lets a user with a completed profile reach the dashboard", async () => {
    renderApp({ user: completedUser, route: "/dashboard" });

    expect(await screen.findByRole("heading", { name: /Хороший день для себя/ })).toBeInTheDocument();
  });

  it("keeps onboarding available to an authenticated user", async () => {
    renderApp({ user: incompleteUser, route: "/onboarding" });

    expect(await screen.findByText("Настроим твой баланс")).toBeInTheDocument();
  });

  it("redirects a completed user away from /login", async () => {
    renderApp({ user: completedUser, route: "/login" });

    expect(await screen.findByRole("heading", { name: /Хороший день для себя/ })).toBeInTheDocument();
  });

  it("redirects an incomplete user away from /register", async () => {
    renderApp({ user: incompleteUser, route: "/register" });

    expect(await screen.findByText("Настроим твой баланс")).toBeInTheDocument();
  });

  it("clears user-scoped query data on logout", async () => {
    const user = userEvent.setup();
    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["private-user-data"], "secret"]],
    });

    expect(queryClient.getQueryData(["private-user-data"])).toBe("secret");
    await user.click(screen.getByRole("button", { name: "Выйти" }));

    await waitFor(() => {
      expect(queryClient.getQueryData(["private-user-data"])).toBeUndefined();
    });
    expect(queryClient.getQueryData(["session"])).toBeNull();
    expect(
      await screen.findByRole("button", { name: "Войти" }),
    ).toBeInTheDocument();
  });

  it.each([
    [
      "сетевой сбой",
      new ApiError(
        0,
        "Не удалось связаться с сервером. Проверьте подключение и попробуйте снова.",
      ),
    ],
    [
      "ответ 500",
      new ApiError(500, "Запрос завершился с ошибкой 500"),
    ],
  ])(
    "keeps the user authorized and offers a retry after a failed logout: %s",
    async (_label, failure) => {
      const user = userEvent.setup();
      vi.mocked(logoutUser).mockRejectedValueOnce(failure);

      const { queryClient } = renderApp({
        user: completedUser,
        route: "/dashboard",
        extraQueries: [[["private-user-data"], "secret"]],
      });

      await user.click(screen.getByRole("button", { name: "Выйти" }));

      expect(
        await screen.findByText(/Не удалось выйти из аккаунта/),
      ).toBeInTheDocument();
      expect(screen.getByText(failure.message)).toBeInTheDocument();
      expect(queryClient.getQueryData(["session"])).toEqual(completedUser);
      expect(
        screen.getByRole("heading", { name: /Хороший день для себя/ }),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: "Повторить выход" }),
      ).toBeInTheDocument();
    },
  );

  it("can retry logout after a failure and clears the session on success", async () => {
    const user = userEvent.setup();
    vi.mocked(logoutUser).mockRejectedValueOnce(
      new ApiError(500, "Запрос завершился с ошибкой 500"),
    );

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["private-user-data"], "secret"]],
    });

    await user.click(screen.getByRole("button", { name: "Выйти" }));
    await user.click(
      await screen.findByRole("button", { name: "Повторить выход" }),
    );

    await waitFor(() => {
      expect(queryClient.getQueryData(["session"])).toBeNull();
    });
    expect(queryClient.getQueryData(["private-user-data"])).toBeUndefined();
    expect(
      await screen.findByRole("button", { name: "Войти" }),
    ).toBeInTheDocument();
  });

  it("treats a 401 logout as a confirmed closed session", async () => {
    const user = userEvent.setup();
    vi.mocked(logoutUser).mockRejectedValueOnce(
      new ApiError(401, "Требуется вход."),
    );

    const { queryClient } = renderApp({
      user: completedUser,
      route: "/dashboard",
      extraQueries: [[["private-user-data"], "secret"]],
    });

    await user.click(screen.getByRole("button", { name: "Выйти" }));

    await waitFor(() => {
      expect(queryClient.getQueryData(["session"])).toBeNull();
    });
    expect(queryClient.getQueryData(["private-user-data"])).toBeUndefined();
    expect(
      await screen.findByRole("button", { name: "Войти" }),
    ).toBeInTheDocument();
    expect(screen.queryByText("Не удалось выйти из аккаунта")).not.toBeInTheDocument();
  });
});
