import { Link } from "react-router-dom";

export function NotFoundPage() {
  return (
    <div className="session-fallback">
      <div className="session-fallback-card">
        <div className="logo" style={{ marginBottom: 24 }}>
          <span className="mark" aria-hidden="true">
            ∿
          </span>
          ритм
        </div>
        <h1>Страница не найдена</h1>
        <p className="muted" style={{ marginBottom: 22 }}>
          Такого маршрута нет. Вернись к своему дню или войди в аккаунт.
        </p>
        <div className="fallback-actions">
          <Link className="primary" to="/dashboard">
            Мой день
          </Link>
          <Link className="secondary" to="/login">
            Войти
          </Link>
        </div>
      </div>
    </div>
  );
}
