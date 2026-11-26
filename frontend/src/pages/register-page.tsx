import { useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { Link, useNavigate } from "react-router-dom";
import { toast } from "sonner";
import { z } from "zod";

import { SetupShell } from "@/components/layout/setup-shell";
import { useUiStore } from "@/stores/use-ui-store";
import { ApiError, validationErrors } from "@/lib/api";
import { registerUser } from "@/lib/auth-api";

const registerSchema = z.object({
  email: z.email("Введите корректный email"),
  password: z
    .string()
    .min(8, "Пароль должен быть не короче 8 символов")
    .max(255, "Пароль слишком длинный"),
});

type RegisterValues = z.infer<typeof registerSchema>;

export function RegisterPage() {
  const [showPassword, setShowPassword] = useState(false);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const mutation = useMutation({ mutationFn: registerUser });
  const form = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { email: "", password: "" },
    mode: "onTouched",
  });

  async function onSubmit(values: RegisterValues) {
    form.clearErrors("root");

    try {
      const payload = await mutation.mutateAsync(values);
      queryClient.clear();
      queryClient.setQueryData(["session"], payload.data);
      useUiStore.getState().reset();
      toast.success("Аккаунт создан. Заполните анкету.");
      navigate("/onboarding", { replace: true });
    } catch (error) {
      if (!(error instanceof ApiError)) {
        form.setError("root", {
          message: "Не удалось создать аккаунт. Попробуйте снова.",
        });
        return;
      }

      const fields = validationErrors(error.payload);
      let hasFieldError = false;

      if (fields.email) {
        form.setError("email", { type: "server", message: fields.email });
        hasFieldError = true;
      }

      if (fields.password) {
        form.setError("password", { type: "server", message: fields.password });
        hasFieldError = true;
      }

      if (!hasFieldError) {
        form.setError("root", { message: error.message });
      }
    }
  }

  const isPending = mutation.isPending || form.formState.isSubmitting;
  const rootError = form.formState.errors.root?.message;
  const emailError = form.formState.errors.email?.message;
  const passwordError = form.formState.errors.password?.message;

  return (
    <SetupShell step={1}>
      <form
        className="setup-form"
        noValidate
        onSubmit={form.handleSubmit(onSubmit)}
      >
        <span className="eyebrow">ДОБРО ПОЖАЛОВАТЬ</span>
        <h1>
          Забота о себе
          <br />
          начинается здесь
        </h1>
        <p className="setup-subtitle">
          Создай свой профиль, а мы поможем настроить ежедневные ориентиры.
        </p>

        <label>
          Электронная почта
          <input
            type="email"
            autoComplete="email"
            placeholder="you@example.com"
            maxLength={254}
            disabled={isPending}
            aria-invalid={Boolean(emailError)}
            aria-describedby={emailError ? "register-email-error" : undefined}
            {...form.register("email")}
          />
        </label>
        {emailError ? (
          <p id="register-email-error" className="field-error">
            {emailError}
          </p>
        ) : null}

        <label htmlFor="register-password">Пароль</label>
        <span className="password-wrap">
          <input
            id="register-password"
            type={showPassword ? "text" : "password"}
            autoComplete="new-password"
            minLength={8}
            maxLength={128}
            placeholder="Не менее 8 символов"
            disabled={isPending}
            aria-invalid={Boolean(passwordError)}
            aria-describedby={
              passwordError ? "register-password-error" : undefined
            }
            {...form.register("password")}
          />
          <button
            type="button"
            aria-label={showPassword ? "Скрыть пароль" : "Показать пароль"}
            aria-pressed={showPassword}
            disabled={isPending}
            onClick={() => setShowPassword((visible) => !visible)}
          >
            {showPassword ? "Скрыть" : "Показать"}
          </button>
        </span>
        {passwordError ? (
          <p id="register-password-error" className="field-error">
            {passwordError}
          </p>
        ) : null}

        {rootError ? (
          <p className="error" role="alert">
            {rootError}
          </p>
        ) : null}

        <button className="primary wide" type="submit" disabled={isPending}>
          {isPending ? "Регистрируем…" : "Зарегистрироваться"}
        </button>

        <p className="auth-switch">
          Уже есть аккаунт? <Link to="/login">Войти</Link>
        </p>
      </form>
    </SetupShell>
  );
}
