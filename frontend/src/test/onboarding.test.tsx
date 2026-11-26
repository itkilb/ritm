import { screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import type { SessionUser } from "@/hooks/use-session";
import { ApiError } from "@/lib/api";
import {
  previewNutritionGoal,
  saveMyProfile,
  type NutritionPreview,
  type ProfileInput,
} from "@/lib/goal-api";
import { renderApp } from "@/test/render-app";

vi.mock("@/lib/goal-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/goal-api")>();

  return {
    ...actual,
    previewNutritionGoal: vi.fn(),
    saveMyProfile: vi.fn(),
    fetchCurrentGoal: vi.fn().mockResolvedValue(null),
  };
});

const baseProfile: ProfileInput = {
  weight_kg: 80,
  height_cm: 180,
  birth_date: "1990-01-15",
  fat_percentage: null,
  gender: "male",
  activity_level: "moderate",
  goal: "lose_weight",
  timezone: "Europe/Moscow",
};

const incompleteUser: SessionUser = {
  id: 1,
  email: "user@example.com",
  name: null,
  profile_completed: false,
  profile: baseProfile,
};

function previewFor(input: ProfileInput): NutritionPreview {
  return {
    ...input,
    calories: 2310,
    protein: 111,
    fat: 64,
    carbs: 222,
    explanation: {
      age: 35,
      bmr: 1755,
      tdee: 2720.25,
      activity_coefficient: 1.55,
      activity_label: "Тренировки 3–5 раз в неделю",
      goal_multiplier: 0.85,
      goal_label: "Похудение",
      lean_mass_kg: null,
      protein_basis: "body_weight",
      principles: ["Белки рассчитаны по весу: вес × 1.6 г/кг."],
    },
  };
}

describe("onboarding flow", () => {
  it("previews, keeps values on back, and confirms only on the final action", async () => {
    const user = userEvent.setup();
    vi.mocked(previewNutritionGoal).mockImplementation(async (input) =>
      previewFor(input),
    );
    vi.mocked(saveMyProfile).mockImplementation(async (input) => ({
      user: {
        ...incompleteUser,
        profile_completed: true,
        profile: input,
      },
      goal: {
        ...previewFor(input),
        id: "goal-1",
        effective_date: "2025-01-15",
      },
    }));

    renderApp({ user: incompleteUser, route: "/onboarding" });

    expect(
      await screen.findByRole("heading", { name: "Настроим твой баланс" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Вес, кг")).toHaveValue(80);

    const weight = screen.getByLabelText("Вес, кг");
    await user.clear(weight);
    await user.type(weight, "90");
    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );

    expect(
      await screen.findByRole("heading", {
        name: /Баланс, с которого/,
      }),
    ).toBeInTheDocument();
    expect(screen.getByText(/2\s*310/)).toBeInTheDocument();
    expect(screen.getByText(/111/)).toBeInTheDocument();
    expect(previewNutritionGoal).toHaveBeenCalledTimes(1);
    expect(
      vi.mocked(previewNutritionGoal).mock.calls.at(-1)?.[0],
    ).toMatchObject({ weight_kg: 90 });
    expect(saveMyProfile).not.toHaveBeenCalled();

    await user.click(
      screen.getByRole("button", { name: "Изменить параметры" }),
    );

    expect(
      screen.getByRole("heading", { name: "Настроим твой баланс" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Вес, кг")).toHaveValue(90);
    expect(saveMyProfile).not.toHaveBeenCalled();

    await user.clear(screen.getByLabelText("Вес, кг"));
    await user.type(screen.getByLabelText("Вес, кг"), "95");
    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );
    expect(
      await screen.findByRole("heading", {
        name: /Баланс, с которого/,
      }),
    ).toBeInTheDocument();
    expect(previewNutritionGoal).toHaveBeenCalledTimes(2);
    expect(
      vi.mocked(previewNutritionGoal).mock.calls.at(-1)?.[0],
    ).toMatchObject({ weight_kg: 95 });

    await user.click(
      screen.getByRole("button", { name: "Перейти к моему дню" }),
    );

    expect(
      await screen.findByRole("heading", { name: "Хороший день для себя ☀" }),
    ).toBeInTheDocument();
    expect(saveMyProfile).toHaveBeenCalledTimes(1);
    expect(vi.mocked(saveMyProfile).mock.calls.at(-1)?.[0]).toMatchObject({
      weight_kg: 95,
    });
  });

  it("invalidates dashboard, weekly, progress and weight caches after a successful goal save", async () => {
    const user = userEvent.setup();
    vi.mocked(previewNutritionGoal).mockImplementation(async (input) =>
      previewFor(input),
    );
    vi.mocked(saveMyProfile).mockImplementation(async (input) => ({
      user: {
        ...incompleteUser,
        profile_completed: true,
        profile: input,
      },
      goal: {
        ...previewFor(input),
        id: "goal-1",
        effective_date: "2025-01-15",
      },
    }));

    const { queryClient } = renderApp({
      user: incompleteUser,
      route: "/onboarding",
    });
    const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");

    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );
    await screen.findByRole("heading", {
      name: /Баланс, с которого/,
    });
    await user.click(
      screen.getByRole("button", { name: "Перейти к моему дню" }),
    );

    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["dashboard"] });
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["weekly"] });
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["progress"] });
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["weight"] });
    expect(queryClient.getQueryData(["goals", "current"])).toMatchObject({
      calories: 2310,
    });
    expect(queryClient.getQueryData(["session"])).toMatchObject({
      profile_completed: true,
    });
  });

  it("prefills the form from the existing server profile", async () => {
    renderApp({
      user: { ...incompleteUser, profile: { ...baseProfile, weight_kg: 72.5 } },
      route: "/onboarding",
    });

    expect(await screen.findByLabelText("Вес, кг")).toHaveValue(72.5);
    expect(screen.getByLabelText("Рост, см")).toHaveValue(180);
    expect(screen.getByLabelText("Дата рождения")).toHaveValue("1990-01-15");
    expect(screen.getByLabelText(/Процент жира, %/)).toHaveValue(null);
    expect(screen.getByLabelText("Пол для расчёта")).toHaveValue("male");
    expect(screen.getByLabelText("Уровень активности")).toHaveValue("moderate");
    expect(
      screen.getByRole("radio", { name: /Похудение/ }),
    ).toBeChecked();
  });

  it("shows client and server validation errors without saving", async () => {
    const user = userEvent.setup();
    renderApp({ user: incompleteUser, route: "/onboarding" });

    await user.clear(screen.getByLabelText("Вес, кг"));
    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );
    expect(await screen.findByText("Укажите вес.")).toBeInTheDocument();
    expect(previewNutritionGoal).not.toHaveBeenCalled();

    await user.type(screen.getByLabelText("Вес, кг"), "80");
    vi.mocked(previewNutritionGoal).mockRejectedValueOnce(
      new ApiError(422, "Ошибка валидации", {
        message: "Ошибка валидации",
        errors: { height_cm: ["Рост должен быть от 120 до 230 см."] },
      }),
    );
    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );

    expect(
      await screen.findByText("Рост должен быть от 120 до 230 см."),
    ).toBeInTheDocument();
    expect(saveMyProfile).not.toHaveBeenCalled();
    expect(
      screen.queryByRole("heading", {
        name: /Баланс, с которого/,
      }),
    ).not.toBeInTheDocument();
  });

  it("blocks a duplicate preview request while it is pending", async () => {
    const user = userEvent.setup();
    let resolvePreview!: (value: NutritionPreview) => void;
    vi.mocked(previewNutritionGoal).mockReturnValue(
      new Promise((resolve) => {
        resolvePreview = resolve;
      }),
    );

    renderApp({ user: incompleteUser, route: "/onboarding" });

    const submit = screen.getByRole("button", {
      name: "Рассчитать мои КБЖУ",
    });
    await user.click(submit);
    await user.click(submit);

    expect(previewNutritionGoal).toHaveBeenCalledTimes(1);
    expect(
      screen.getByRole("button", { name: "Рассчитываем…" }),
    ).toBeDisabled();

    resolvePreview(previewFor(baseProfile));

    expect(
      await screen.findByRole("heading", {
        name: /Баланс, с которого/,
      }),
    ).toBeInTheDocument();
  });

  it("returns to the form with server errors when confirmation fails", async () => {
    const user = userEvent.setup();
    vi.mocked(previewNutritionGoal).mockImplementation(async (input) =>
      previewFor(input),
    );
    vi.mocked(saveMyProfile).mockRejectedValueOnce(
      new ApiError(422, "Ошибка валидации", {
        message: "Ошибка валидации",
        errors: { weight_kg: ["Вес должен быть от 35 до 300 кг."] },
      }),
    );

    renderApp({ user: incompleteUser, route: "/onboarding" });

    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );
    await screen.findByRole("heading", {
      name: /Баланс, с которого/,
    });

    await user.click(
      screen.getByRole("button", { name: "Перейти к моему дню" }),
    );

    expect(
      await screen.findByText("Вес должен быть от 35 до 300 кг."),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", { name: "Настроим твой баланс" }),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Вес, кг")).toHaveValue(80);
    expect(
      screen.queryByRole("heading", { name: /Хороший день для себя/ }),
    ).not.toBeInTheDocument();
  });

  it("keeps the result and offers a retry when the save fails over the network", async () => {
    const user = userEvent.setup();
    vi.mocked(previewNutritionGoal).mockImplementation(async (input) =>
      previewFor(input),
    );
    vi.mocked(saveMyProfile).mockRejectedValueOnce(
      new ApiError(
        0,
        "Не удалось связаться с сервером. Проверьте подключение и попробуйте снова.",
      ),
    );

    renderApp({ user: incompleteUser, route: "/onboarding" });

    await user.click(
      screen.getByRole("button", { name: "Рассчитать мои КБЖУ" }),
    );
    await screen.findByRole("heading", {
      name: /Баланс, с которого/,
    });
    await user.click(
      screen.getByRole("button", { name: "Перейти к моему дню" }),
    );

    expect(
      await screen.findByText(/Не удалось сохранить цель/),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Повторить сохранение" }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("heading", {
        name: /Баланс, с которого/,
      }),
    ).toBeInTheDocument();
  });
});
