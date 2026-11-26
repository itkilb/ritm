import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { ApiError } from "@/lib/api";
import { loginUser, registerUser } from "@/lib/auth-api";
import { useUiStore } from "@/stores/use-ui-store";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/auth-api", () => ({
  registerUser: vi.fn(),
  loginUser: vi.fn(),
  logoutUser: vi.fn(),
}));

const registeredUser = {
  id: 10,
  email: "new@example.com",
  name: null,
  profile_completed: false,
};

async function fillCredentials(email: string, password: string) {
  const user = userEvent.setup();

  await user.type(screen.getByLabelText("Электронная почта"), email);
  await user.type(screen.getByLabelText("Пароль"), password);

  return user;
}

describe("register form", () => {

  it("renders the reference setup composition without a demo-account warning", async () => {
    renderApp({ user: null, route: "/register" });

    expect(
      await screen.findByRole("heading", { name: /Забота о себе/ }),
    ).toBeInTheDocument();
    expect(screen.getByText("ПИТАНИЕ. ДВИЖЕНИЕ. ТЫ.")).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: /Твой ритм/ }),
    ).toBeInTheDocument();
    expect(screen.getByText("В своём темпе")).toBeInTheDocument();
    expect(
      screen.getByRole("link", { name: "Войти" }),
    ).toHaveAttribute("href", "/login");
    expect(screen.queryByText(/прототип/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/аккаунт не создаётся/i)).not.toBeInTheDocument();
  });

  it("toggles the password visibility without submitting the form", async () => {
    renderApp({ user: null, route: "/register" });
    const user = userEvent.setup();
    const password = screen.getByLabelText("Пароль");
    const toggle = screen.getByRole("button", { name: "Показать пароль" });

    expect(password).toHaveAttribute("type", "password");
    await user.click(toggle);
    expect(password).toHaveAttribute("type", "text");
    expect(screen.getByRole("button", { name: "Скрыть пароль" })).toBeInTheDocument();
    expect(registerUser).not.toHaveBeenCalled();
  });

  it("shows a field error for a duplicate email", async () => {
    vi.mocked(registerUser).mockRejectedValue(
      new ApiError(422, "Ошибка валидации", {
        message: "Ошибка валидации",
        errors: { email: ["Этот email уже зарегистрирован."] },
      }),
    );
    renderApp({ user: null, route: "/register" });

    const user = await fillCredentials("taken@example.com", "password123");
    await user.click(
      screen.getByRole("button", { name: "Зарегистрироваться" }),
    );

    expect(
      await screen.findByText("Этот email уже зарегистрирован."),
    ).toBeInTheDocument();
  });

  it("shows a rate-limit error and keeps the form usable", async () => {
    vi.mocked(registerUser).mockRejectedValue(
      new ApiError(429, "Слишком много попыток регистрации. Попробуйте позже."),
    );
    renderApp({ user: null, route: "/register" });

    const user = await fillCredentials("new@example.com", "password123");
    await user.click(
      screen.getByRole("button", { name: "Зарегистрироваться" }),
    );

    expect(
      await screen.findByText(
        "Слишком много попыток регистрации. Попробуйте позже.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Зарегистрироваться" }),
    ).toBeEnabled();
  });

  it("guards against a duplicate submit while the request is pending", async () => {
    let resolveRequest!: (value: { data: typeof registeredUser }) => void;
    vi.mocked(registerUser).mockReturnValue(
      new Promise((resolve) => {
        resolveRequest = resolve;
      }),
    );
    renderApp({ user: null, route: "/register" });

    const user = await fillCredentials("new@example.com", "password123");
    const submit = screen.getByRole("button", { name: "Зарегистрироваться" });
    await user.click(submit);
    await user.click(submit);

    expect(registerUser).toHaveBeenCalledTimes(1);
    expect(screen.getByRole("button", { name: "Регистрируем…" })).toBeDisabled();

    resolveRequest({ data: registeredUser });
    expect(await screen.findByText("Настроим твой баланс")).toBeInTheDocument();
  });
});

describe("login form", () => {
  it("opens the dashboard when the profile is completed", async () => {
    vi.mocked(loginUser).mockResolvedValue({
      data: { ...registeredUser, profile_completed: true },
    });
    renderApp({ user: null, route: "/login" });

    const user = await fillCredentials("done@example.com", "password123");
    await user.click(screen.getByRole("button", { name: "Войти" }));

    expect(await screen.findByRole("heading", { name: /Хороший день для себя/ })).toBeInTheDocument();
  });

  it("opens onboarding when the profile is incomplete", async () => {
    vi.mocked(loginUser).mockResolvedValue({ data: registeredUser });
    renderApp({ user: null, route: "/login" });

    const user = await fillCredentials("new@example.com", "password123");
    await user.click(screen.getByRole("button", { name: "Войти" }));

    expect(await screen.findByText("Настроим твой баланс")).toBeInTheDocument();
  });

  it("clears the selected day when a new account logs in", async () => {
    vi.mocked(loginUser).mockResolvedValue({
      data: { ...registeredUser, profile_completed: true },
    });
    renderApp({ user: null, route: "/login" });
    useUiStore.getState().setSelectedDate("2020-01-01");

    const user = await fillCredentials("switch@example.com", "password123");
    await user.click(screen.getByRole("button", { name: "Войти" }));

    expect(await screen.findByRole("heading", { name: /Хороший день для себя/ })).toBeInTheDocument();
    expect(useUiStore.getState().selectedDate).toBeNull();
  });

  it("shows invalid credentials next to the email field", async () => {
    vi.mocked(loginUser).mockRejectedValue(
      new ApiError(422, "Ошибка валидации", {
        message: "Ошибка валидации",
        errors: { email: ["Неверный email или пароль."] },
      }),
    );
    renderApp({ user: null, route: "/login" });

    const user = await fillCredentials("wrong@example.com", "wrong-password");
    await user.click(screen.getByRole("button", { name: "Войти" }));

    expect(await screen.findByText("Неверный email или пароль.")).toBeInTheDocument();
  });

  it("shows a network/rate-limit failure as a form-level error", async () => {
    vi.mocked(loginUser).mockRejectedValue(
      new ApiError(0, "Не удалось связаться с сервером. Проверьте подключение и попробуйте снова."),
    );
    renderApp({ user: null, route: "/login" });

    const user = await fillCredentials("new@example.com", "password123");
    await user.click(screen.getByRole("button", { name: "Войти" }));

    expect(
      await screen.findByText(
        "Не удалось связаться с сервером. Проверьте подключение и попробуйте снова.",
      ),
    ).toBeInTheDocument();
  });
});
