import type { ReactNode } from "react";
import { Navigate, useLocation } from "react-router-dom";

import { useSession } from "@/hooks/use-session";

export function SessionSkeleton() {
  return (
    <div className="session-fallback">
      <div
        className="session-fallback-card"
        aria-busy="true"
        aria-label="Проверка сессии"
      >
        <span className="loading-line" style={{ width: 120, height: 26 }} />
        <span
          className="loading-line"
          style={{ width: "72%", height: 14, marginTop: 16 }}
        />
        <span
          className="loading-line"
          style={{ width: "100%", height: 180, marginTop: 24 }}
        />
      </div>
    </div>
  );
}

function SessionError({
  message,
  onRetry,
}: {
  message: string;
  onRetry: () => void;
}) {
  return (
    <div className="session-fallback">
      <div className="session-fallback-card">
        <h1>Не удалось проверить сессию</h1>
        <div className="inline-alert" role="alert">
          <span>{message}</span>
          <button type="button" className="text-btn" onClick={onRetry}>
            Повторить
          </button>
        </div>
      </div>
    </div>
  );
}

export function RequireAuth({ children }: { children: ReactNode }) {
  const location = useLocation();
  // Completion is server-owned (GET /v1/me); the client never infers it locally.
  const { data, isError, error, isLoading, refetch } = useSession();

  if (isLoading) {
    return <SessionSkeleton />;
  }

  if (isError) {
    return (
      <SessionError
        message={error instanceof Error ? error.message : "Сервер недоступен."}
        onRetry={() => void refetch()}
      />
    );
  }

  if (!data) {
    return (
      <Navigate
        to="/login"
        replace
        state={{ from: `${location.pathname}${location.search}` }}
      />
    );
  }

  return <>{children}</>;
}

export function RequireCompletedProfile({ children }: { children: ReactNode }) {
  const location = useLocation();
  const { data, isError, error, isLoading, refetch } = useSession();

  if (isLoading) {
    return <SessionSkeleton />;
  }

  if (isError) {
    return (
      <SessionError
        message={error instanceof Error ? error.message : "Сервер недоступен."}
        onRetry={() => void refetch()}
      />
    );
  }

  if (!data) {
    return (
      <Navigate
        to="/login"
        replace
        state={{ from: `${location.pathname}${location.search}` }}
      />
    );
  }

  if (!data.profile_completed) {
    return <Navigate to="/onboarding" replace />;
  }

  return <>{children}</>;
}

export function GuestOnly({ children }: { children: ReactNode }) {
  const { data, isError, error, isLoading, refetch } = useSession();

  if (isLoading) {
    return <SessionSkeleton />;
  }

  if (isError) {
    return (
      <SessionError
        message={error instanceof Error ? error.message : "Сервер недоступен."}
        onRetry={() => void refetch()}
      />
    );
  }

  if (data) {
    return <Navigate to={data.profile_completed ? "/dashboard" : "/onboarding"} replace />;
  }

  return <>{children}</>;
}
