import type { SessionUser } from "@/hooks/use-session";

export type DashboardNav = "today" | "food" | "activity" | "workouts" | "progress";

const NAV_ITEMS: Array<{
  key: DashboardNav;
  glyph: string;
  label: string;
}> = [
  { key: "today", glyph: "◫", label: "Мой день" },
  { key: "food", glyph: "◷", label: "Питание" },
  { key: "activity", glyph: "♧", label: "Активность" },
  { key: "workouts", glyph: "⌁", label: "Тренировки" },
  { key: "progress", glyph: "▥", label: "Прогресс" },
];

export function Sidebar({
  user,
  active,
  onNavigate,
  onEditProfile,
  onLogout,
  logoutPending,
}: {
  user: SessionUser | null | undefined;
  active: DashboardNav;
  onNavigate: (nav: DashboardNav) => void;
  onEditProfile: () => void;
  onLogout: () => void;
  logoutPending: boolean;
}) {
  const email = user?.email ?? "";
  const profileName = email ? email.split("@")[0] : "Мой профиль";
  const avatar = profileName.slice(0, 1) || "М";

  return (
    <aside className="sidebar">
      <div className="logo">
        <span className="mark" aria-hidden="true">
          ∿
        </span>
        ритм
      </div>
      <div className="tag">В БАЛАНСЕ С СОБОЙ</div>

      <nav className="side-nav" aria-label="Разделы дня">
        {NAV_ITEMS.map((item) => (
          <button
            key={item.key}
            type="button"
            className={active === item.key ? "active" : undefined}
            aria-current={active === item.key ? "page" : undefined}
            onClick={() => onNavigate(item.key)}
          >
            <span aria-hidden="true">
              {item.glyph}
            </span>
            <span>{item.label}</span>
          </button>
        ))}
      </nav>

      <div className="side-bottom">
        <div className="mini">
          Маленькие шаги.
          <br />
          <b>Большие перемены.</b>
          <br />
          <span className="muted">Каждый день имеет значение.</span>
        </div>

        <div className="profile-row">
          <button
            type="button"
            className="profile profile-button"
            onClick={onEditProfile}
            aria-label="Открыть параметры и цель"
          >
            <span className="avatar" aria-hidden="true">
              {avatar}
            </span>
            <span className="profile-copy">
              <b>{profileName}</b>
              <span className="muted">Параметры и цель</span>
            </span>
          </button>
          <button
            type="button"
            className="side-logout"
            onClick={onLogout}
            disabled={logoutPending}
          >
            {logoutPending ? "Выходим…" : "Выйти"}
          </button>
        </div>
      </div>
    </aside>
  );
}
