import { useNavigate } from "react-router-dom";

export function MobileViewSwitch({
  active,
}: {
  active: "day" | "workouts" | "progress";
}) {
  const navigate = useNavigate();

  return (
    <div className="mobile-view-switch" role="navigation" aria-label="Разделы">
      <button
        type="button"
        className={active === "day" ? "selected" : undefined}
        aria-current={active === "day" ? "page" : undefined}
        aria-label="Мой день — мобильная навигация"
        onClick={() => navigate("/dashboard")}
      >
        Мой день
      </button>
      <button
        type="button"
        className={active === "workouts" ? "selected" : undefined}
        aria-current={active === "workouts" ? "page" : undefined}
        aria-label="Тренировки — мобильная навигация"
        onClick={() => navigate("/workouts")}
      >
        Тренировки
      </button>
      <button
        type="button"
        className={active === "progress" ? "selected" : undefined}
        aria-current={active === "progress" ? "page" : undefined}
        aria-label="Прогресс — мобильная навигация"
        onClick={() => navigate("/progress")}
      >
        Прогресс
      </button>
    </div>
  );
}
