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
import { loginUser } from "@/lib/auth-api";

const loginSchema = z.object({
  email: z.email("Введите корректный email"),
  password: z.string().min(1, "Введите пароль"),
});

type LoginValues = z.infer<typeof loginSchema>;

export function LoginPage() {
  const [showPassword, setShowPassword] = useState(false);
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const mutation = useMutation({ mutationFn: loginUser });
  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: "", password: "" },
    mode: "onTouched",
  });

  async function onSubmit(values: LoginValues) {
    form.clearErrors("root");

    try {
      const payload = await mutation.mutateAsync(values);
      queryClient.clear();
      queryClient.setQueryData(["session"], payload.data);
      useUiStore.getState().reset();
      toast.success("Вы вошли в аккаунт");
      navigate(payload.data.profile_completed ? "/dashboard" : "/onboarding", {
        replace: true,
      });
    } catch (error) {
      if (!(error instanceof ApiError)) {
        form.setError("root", {
          message: "Не удалось выполнить вход. Попробуйте снова.",
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
    <SetupShell step={1} label="Вход в аккаунт">
      <form
        className="setup-form"
        noValidate
        onSubmit={form.handleSubmit(onSubmit)}
      >
        <span className="eyebrow">С ВОЗВРАЩЕНИЕМ</span>
        <h1>
          Продолжим
          <br />
          твой ритм
        </h1>
        <p className="setup-subtitle">
          Войди, чтобы вернуться к своему дню. Всё, что ты сохранил, уже на
          месте.
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
            aria-describedby={emailError ? "login-email-error" : undefined}
            {...form.register("email")}
          />
        </label>
        {emailError ? (
          <p id="login-email-error" className="field-error">
            {emailError}
          </p>
        ) : null}

        <label htmlFor="login-password">Пароль</label>
        <span className="password-wrap">
          <input
            id="login-password"
            type={showPassword ? "text" : "password"}
            autoComplete="current-password"
            placeholder="Твой пароль"
            disabled={isPending}
            aria-invalid={Boolean(passwordError)}
            aria-describedby={
              passwordError ? "login-password-error" : undefined
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
          <p id="login-password-error" className="field-error">
            {passwordError}
          </p>
        ) : null}

        {rootError ? (
          <p className="error" role="alert">
            {rootError}
          </p>
        ) : null}

        <button className="primary wide" type="submit" disabled={isPending}>
          {isPending ? "Входим…" : "Войти"}
        </button>

        <p className="auth-switch">
          Нет аккаунта? <Link to="/register">Зарегистрироваться</Link>
        </p>
      </form>
    </SetupShell>
  );
}
