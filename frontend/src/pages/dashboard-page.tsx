import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";
import { useLocation, useNavigate } from "react-router-dom";
import { toast } from "sonner";

import { ActivityCard } from "@/components/dashboard/activity-card";
import { AddEntryDialog } from "@/components/dashboard/add-entry-dialog";
import { CalorieBalanceCard } from "@/components/dashboard/calorie-balance-card";
import { DayOverview } from "@/components/dashboard/day-overview";
import { FoodDiaryCard } from "@/components/dashboard/food-diary-card";
import { MacrosCard } from "@/components/dashboard/macros-card";
import { Sidebar, type DashboardNav } from "@/components/dashboard/sidebar";
import { TipCard } from "@/components/dashboard/tip-card";
import { WaterCard } from "@/components/dashboard/water-card";
import { WeekCard } from "@/components/dashboard/week-card";
import { MobileViewSwitch } from "@/components/layout/mobile-view-switch";
import {
  dashboardQueryKey,
  useDashboard,
  useWeeklyStats,
  weeklyQueryKey,
} from "@/hooks/use-dashboard";
import { useLogout } from "@/hooks/use-logout";
import { useSession } from "@/hooks/use-session";
import {
  fetchDashboard,
  fetchWeeklyStats,
  type DashboardData,
  type MealType,
  type WeeklyData,
} from "@/lib/dashboard-api";
import { addIsoDays, startOfIsoWeek } from "@/lib/date";
import { useUiStore } from "@/stores/use-ui-store";

type EntryType = "food" | "activity";

export function DashboardPage() {
  const selectedDate = useUiStore((state) => state.selectedDate);
  const setSelectedDate = useUiStore((state) => state.setSelectedDate);
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const location = useLocation();
  const session = useSession();
  const logout = useLogout();

  const dashboard = useDashboard(selectedDate);
  const weekly = useWeeklyStats(
    selectedDate ? startOfIsoWeek(selectedDate) : null,
  );

  const [activeNav, setActiveNav] = useState<DashboardNav>("today");
  const [entryDialog, setEntryDialog] = useState<{
    type: EntryType;
    mealType: MealType | null;
    nonce: number;
  } | null>(null);

  const selectedDateRef = useRef(selectedDate);
  const syncingTodayRef = useRef(false);
  const focusHandledRef = useRef(false);

  const effectiveDate =
    selectedDate ?? dashboard.data?.date ?? weekly.data?.today ?? null;
  const today = dashboard.data?.today ?? weekly.data?.today ?? null;
  const highlightedDate = selectedDate ?? dashboard.data?.date ?? null;

  useEffect(() => {
    selectedDateRef.current = selectedDate;
  }, [selectedDate]);

  useEffect(() => {
    const focus = (location.state as { focus?: DashboardNav } | null)?.focus;

    if (!focus) {
      focusHandledRef.current = false;
      return;
    }

    if (focusHandledRef.current) {
      return;
    }

    focusHandledRef.current = true;
    setActiveNav(focus);

    if (focus === "progress") {
      navigate("/progress");
      return;
    }

    if (focus === "food" || focus === "activity") {
      const targetId = focus === "food" ? "food-diary" : "activity";
      window.setTimeout(() => {
        document
          .getElementById(targetId)
          ?.scrollIntoView?.({ behavior: "smooth", block: "start" });
      }, 0);
    }

    navigate(location.pathname, { replace: true, state: null });
  }, [location.pathname, location.state, navigate]);

  useEffect(() => {
    setEntryDialog(null);
  }, [selectedDate]);

  function changeSelectedDate(date: string | null) {
    selectedDateRef.current = date;
    setSelectedDate(date);
  }

  function openEntry(type: EntryType, mealType: MealType | null = null) {
    if (!effectiveDate) {
      return;
    }

    setEntryDialog({ type, mealType, nonce: Date.now() });
  }

  function scrollToTop() {
    try {
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch {
    }
  }

  function handleNavigate(nav: DashboardNav) {
    setActiveNav(nav);

    if (nav === "today") {
      scrollToTop();
      return;
    }

    if (nav === "workouts") {
      navigate("/workouts");
      return;
    }

    if (nav === "progress") {
      navigate("/progress");
      return;
    }

    const target = document.getElementById(
      nav === "food" ? "food-diary" : "activity",
    );
    target?.scrollIntoView?.({ behavior: "smooth", block: "start" });
  }

  function goToPreviousWeek() {
    if (effectiveDate) {
      changeSelectedDate(addIsoDays(effectiveDate, -7));
    }
  }

  function goToNextWeek() {
    if (effectiveDate) {
      changeSelectedDate(addIsoDays(effectiveDate, 7));
    }
  }

  // Cached "today" may be stale across midnight or a different profile timezone.
  async function goToToday() {
    if (syncingTodayRef.current) {
      return;
    }

    syncingTodayRef.current = true;
    const selectionAtStart = selectedDateRef.current;

    try {
      const [freshDashboard] = await Promise.all([
        queryClient.fetchQuery<DashboardData>({
          queryKey: dashboardQueryKey(null),
          queryFn: () => fetchDashboard(null),
          staleTime: 0,
        }),
        queryClient.fetchQuery<WeeklyData>({
          queryKey: weeklyQueryKey(null),
          queryFn: () => fetchWeeklyStats(null),
          staleTime: 0,
        }),
      ]);

      if (selectedDateRef.current !== selectionAtStart) {
        return;
      }

      if (freshDashboard.today) {
        changeSelectedDate(freshDashboard.today);
      }
    } catch {
    } finally {
      syncingTodayRef.current = false;
    }
  }

  async function handleLogout() {
    try {
      await logout.mutateAsync();
      toast.success("Вы вышли из аккаунта");
      navigate("/login", { replace: true });
    } catch {
      toast.error("Не удалось выйти. Сессия на сервере могла остаться активной.");
    }
  }

  const logoutErrorMessage =
    logout.error instanceof Error
      ? logout.error.message
      : "Сервер недоступен. Попробуйте снова.";

  return (
    <>
      <Sidebar
        user={session.data}
        active={activeNav}
        onNavigate={handleNavigate}
        onEditProfile={() => navigate("/onboarding")}
        onLogout={() => void handleLogout()}
        logoutPending={logout.isPending}
      />

      <main className="dash-main">
        <div className="mobile-logo">
          <span aria-hidden="true">∿</span> ритм
        </div>
        <MobileViewSwitch active="day" />

        <header className="dash-header">
          <div>
            <div className="eyebrow">ТВОЙ ЕЖЕДНЕВНЫЙ БАЛАНС</div>
            <h1 aria-label="Хороший день для себя ☀">
              Хороший день для себя{" "}
              <span style={{ fontSize: 26 }} aria-hidden="true">
                ☀
              </span>
            </h1>
            <div className="muted">
              Забота о себе начинается с простых привычек.
            </div>
          </div>

          <div className="header-controls">
            <input
              className="date"
              type="date"
              aria-label="Выбрать день"
              value={effectiveDate ?? ""}
              onChange={(event) => {
                changeSelectedDate(event.target.value || null);
              }}
            />
            <button
              type="button"
              className="mobile-logout"
              aria-label="Выйти из аккаунта"
              onClick={() => void handleLogout()}
              disabled={logout.isPending}
            >
              {logout.isPending ? "Выходим…" : "Выйти"}
            </button>
          </div>
        </header>

        {logout.isError ? (
          <div className="inline-alert" role="alert" style={{ marginTop: 18 }}>
            <span>
              <b>Не удалось выйти из аккаунта.</b> {logoutErrorMessage}
            </span>
            <button
              type="button"
              className="text-btn"
              onClick={() => void handleLogout()}
              disabled={logout.isPending}
            >
              {logout.isPending ? "Повторяем…" : "Повторить выход"}
            </button>
          </div>
        ) : null}

        <WeekCard
          weekly={weekly.data}
          isLoading={weekly.isLoading}
          isError={weekly.isError}
          errorMessage={
            weekly.error instanceof Error ? weekly.error.message : undefined
          }
          selectedDate={highlightedDate}
          fallbackToday={today}
          onSelectDay={(date) => changeSelectedDate(date)}
          onPrev={goToPreviousWeek}
          onNext={goToNextWeek}
          onToday={goToToday}
          onRetry={() => void weekly.refetch()}
        />

        <DayOverview
          date={effectiveDate}
          today={today}
          onAddRecord={() => openEntry("food")}
        />

        {dashboard.isError ? (
          <div className="inline-alert" role="alert">
            <span>
              Не удалось загрузить день.{" "}
              {dashboard.error instanceof Error
                ? dashboard.error.message
                : "Сервер недоступен."}
            </span>
            <button
              type="button"
              className="text-btn"
              onClick={() => void dashboard.refetch()}
            >
              Повторить
            </button>
          </div>
        ) : (
          <>
            <div className="grid">
              <CalorieBalanceCard
                data={dashboard.data}
                isLoading={dashboard.isLoading}
              />
              <MacrosCard
                data={dashboard.data}
                isLoading={dashboard.isLoading}
                onEditGoal={() => navigate("/onboarding")}
              />
              <ActivityCard
                data={dashboard.data}
                isLoading={dashboard.isLoading}
                onAddActivity={() => openEntry("activity")}
                onOpenWorkouts={() => navigate("/workouts")}
              />
            </div>

            <div className="lower">
              <FoodDiaryCard
                data={dashboard.data}
                isLoading={dashboard.isLoading}
                onAddFood={(mealType) => openEntry("food", mealType)}
              />
              <div className="lower-side">
                <WaterCard
                  data={dashboard.data}
                  isLoading={dashboard.isLoading}
                  date={effectiveDate}
                />
                <TipCard />
              </div>
            </div>
          </>
        )}

        <footer className="dash-footer">
          <span>Ритм — забота о себе без спешки</span>
          <span>Полноценное приложение · данные аккаунта хранятся на сервере</span>
        </footer>
      </main>

      {entryDialog && effectiveDate ? (
        <AddEntryDialog
          key={`${entryDialog.type}-${entryDialog.mealType ?? "none"}-${entryDialog.nonce}-${effectiveDate}`}
          date={effectiveDate}
          initialType={entryDialog.type}
          initialMealType={entryDialog.mealType}
          onClose={() => setEntryDialog(null)}
        />
      ) : null}
    </>
  );
}
