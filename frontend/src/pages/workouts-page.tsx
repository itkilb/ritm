import { useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef, useState } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { toast } from "sonner";

import { Sidebar, type DashboardNav } from "@/components/dashboard/sidebar";
import { MobileViewSwitch } from "@/components/layout/mobile-view-switch";
import { Skeleton } from "@/components/ui/skeleton";
import { WorkoutCard } from "@/components/workouts/workout-card";
import { WorkoutEditor, type WorkoutEditorState } from "@/components/workouts/workout-editor";
import { WorkoutIcon } from "@/components/workouts/workout-icon";
import { WorkoutStats } from "@/components/workouts/workout-stats";
import { WorkoutWeekCard } from "@/components/workouts/workout-week-card";
import { useLogout } from "@/hooks/use-logout";
import { useSession, type SessionUser } from "@/hooks/use-session";
import {
  fetchWorkout,
  fetchWorkoutDiary,
  type Workout,
  type WorkoutType,
} from "@/lib/workout-api";
import {
  useCreateWorkout,
  useDeleteWorkout,
  useWorkoutDiary,
  workoutQueryKey,
} from "@/hooks/use-workouts";
import { addIsoDays, formatIsoDate } from "@/lib/date";
import {
  workoutToRestoreInput,
  WORKOUT_TYPE_LABELS,
} from "@/lib/workout-form";
import { useUiStore } from "@/stores/use-ui-store";

type WorkoutFilter = "all" | "strength" | "cardio";

const FILTERS: Array<{ key: WorkoutFilter; label: string }> = [
  { key: "all", label: "Все" },
  { key: "strength", label: "Силовые" },
  { key: "cardio", label: "Кардио" },
];

function WorkoutListSkeleton() {
  return (
    <div aria-busy="true" aria-label="Загрузка тренировок">
      {[0, 1].map((index) => (
        <section className="card workout-card" key={index}>
          <Skeleton className="loading-line" style={{ width: "45%", height: 22 }} />
          <Skeleton
            className="loading-line"
            style={{ width: "75%", height: 14, marginTop: 16 }}
          />
          <Skeleton
            className="loading-line"
            style={{ width: "55%", height: 14, marginTop: 12 }}
          />
        </section>
      ))}
    </div>
  );
}

export function WorkoutsPage() {
  const selectedDate = useUiStore((state) => state.selectedDate);
  const setSelectedDate = useUiStore((state) => state.setSelectedDate);
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const session = useSession();
  const logout = useLogout();
  const diary = useWorkoutDiary(selectedDate);
  const deleteMutation = useDeleteWorkout();
  const restoreMutation = useCreateWorkout();

  const [filter, setFilter] = useState<WorkoutFilter>("all");
  const [editor, setEditor] = useState<WorkoutEditorState | null>(null);
  const selectedDateRef = useRef(selectedDate);
  const syncingTodayRef = useRef(false);
  const pendingEditorRef = useRef<WorkoutEditorState | null>(null);
  const openedWorkoutRef = useRef<string | null>(null);

  const effectiveDate = selectedDate ?? diary.data?.date ?? null;
  const today = diary.data?.today ?? null;

  function isSessionActive(expectedId: SessionUser["id"] | null): boolean {
    if (expectedId === null) {
      return false;
    }

    const current = queryClient.getQueryData<SessionUser | null>(["session"]);

    return (
      current !== null &&
      current !== undefined &&
      String(current.id) === String(expectedId)
    );
  }

  useEffect(() => {
    selectedDateRef.current = selectedDate;
  }, [selectedDate]);

  useEffect(() => {
    if (pendingEditorRef.current !== null) {
      setEditor(pendingEditorRef.current);
      pendingEditorRef.current = null;
      return;
    }

    setEditor(null);
  }, [selectedDate]);

  useEffect(() => {
    const workoutId = searchParams.get("workout");

    if (!workoutId || openedWorkoutRef.current === workoutId) {
      return;
    }

    openedWorkoutRef.current = workoutId;
    const sessionAtStart = session.data?.id ?? null;

    void fetchWorkout(workoutId)
      .then((workout) => {
        if (!isSessionActive(sessionAtStart)) {
          return;
        }

        const next = new URLSearchParams(searchParams);
        next.delete("workout");
        setSearchParams(next, { replace: true });

        const nextEditor: WorkoutEditorState = {
          mode: "edit",
          type: workout.type,
          workout,
          nonce: Date.now(),
        };

        if (selectedDateRef.current !== workout.date) {
          pendingEditorRef.current = nextEditor;
          changeSelectedDate(workout.date);
          return;
        }

        setEditor(nextEditor);
      })
      .catch(() => {
        if (!isSessionActive(sessionAtStart)) {
          return;
        }

        toast.error("Не удалось открыть тренировку.");
        const next = new URLSearchParams(searchParams);
        next.delete("workout");
        setSearchParams(next, { replace: true });
      });
    // A late response must not open a workout under a new account.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams, session.data?.id, setSearchParams]);

  function changeSelectedDate(date: string | null) {
    selectedDateRef.current = date;
    setSelectedDate(date);
  }

  function scrollToTop() {
    try {
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch {
    }
  }

  function handleNavigate(nav: DashboardNav) {
    if (nav === "workouts") {
      scrollToTop();
      return;
    }

    if (nav === "today") {
      navigate("/dashboard");
      return;
    }

    if (nav === "progress") {
      navigate("/progress");
      return;
    }

    navigate("/dashboard", { state: { focus: nav } });
  }

  function openEditor(type: WorkoutType, workout?: Workout, repeat = false) {
    if (!effectiveDate) {
      return;
    }

    setEditor({
      mode: repeat ? "repeat" : workout ? "edit" : "create",
      type,
      workout,
      nonce: Date.now(),
    });
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

  async function goToToday() {
    if (syncingTodayRef.current) {
      return;
    }

    syncingTodayRef.current = true;
    const selectionAtStart = selectedDateRef.current;
    const sessionAtStart = session.data?.id ?? null;

    try {
      const fresh = await queryClient.fetchQuery({
        queryKey: workoutQueryKey(null),
        queryFn: () => fetchWorkoutDiary(null),
        staleTime: 0,
      });

      if (
        !isSessionActive(sessionAtStart) ||
        selectedDateRef.current !== selectionAtStart
      ) {
        return;
      }

      if (fresh.today) {
        changeSelectedDate(fresh.today);
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

  function restoreWorkout(
    workout: Workout,
    sessionAtDelete: SessionUser["id"] | null,
  ) {
    if (!isSessionActive(sessionAtDelete)) {
      return;
    }

    restoreMutation.mutate(workoutToRestoreInput(workout), {
      onSuccess: () => {
        if (isSessionActive(sessionAtDelete)) {
          toast.success("Тренировка восстановлена");
        }
      },
      onError: () => {
        if (isSessionActive(sessionAtDelete)) {
          toast.error("Не удалось восстановить тренировку.");
        }
      },
    });
  }

  function handleDelete(workout: Workout) {
    const sessionAtDelete = session.data?.id ?? null;

    deleteMutation.mutate(
      { id: workout.id, date: workout.date },
      {
        onSuccess: () => {
          if (!isSessionActive(sessionAtDelete)) {
            return;
          }

          toast.success("Тренировка удалена", {
            action: {
              label: "Отменить удаление",
              onClick: () => restoreWorkout(workout, sessionAtDelete),
            },
          });
        },
        onError: (error) => {
          if (!isSessionActive(sessionAtDelete)) {
            return;
          }

          toast.error(
            error instanceof Error
              ? error.message
              : "Не удалось удалить тренировку.",
          );
        },
      },
    );
  }

  function handleSaved(workout: Workout) {
    changeSelectedDate(workout.date);
    setFilter("all");
    setEditor(null);
  }

  const dailyWorkouts = (diary.data?.workouts ?? []).filter((workout) => {
    if (filter === "all") {
      return true;
    }

    return filter === "strength"
      ? workout.type === "strength"
      : workout.type !== "strength";
  });

  const title =
    effectiveDate === today
      ? "Тренировки сегодня"
      : effectiveDate
        ? formatIsoDate(effectiveDate, { day: "numeric", month: "long", weekday: "long" })
        : "Тренировки";

  const logoutErrorMessage =
    logout.error instanceof Error
      ? logout.error.message
      : "Сервер недоступен. Попробуйте снова.";

  const deletingId = deleteMutation.isPending
    ? deleteMutation.variables?.id
    : undefined;

  return (
    <>
      <Sidebar
        user={session.data}
        active="workouts"
        onNavigate={handleNavigate}
        onEditProfile={() => navigate("/onboarding")}
        onLogout={() => void handleLogout()}
        logoutPending={logout.isPending}
      />

      <main className="dash-main">
        <div className="mobile-logo">
          <span aria-hidden="true">∿</span> ритм
        </div>
        <MobileViewSwitch active="workouts" />

        <header className="dash-header">
          <div>
            <div className="eyebrow">ТВОЙ ЕЖЕДНЕВНЫЙ БАЛАНС</div>
            <h1 aria-label="Хороший день для себя ☀">
              Хороший день для себя{" "}
              <span style={{ fontSize: 26 }} aria-hidden="true">
                ☀
              </span>
            </h1>
            <div className="muted">Забота о себе начинается с простых привычек.</div>
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

        <div className="training-heading">
          <div>
            <div className="eyebrow">ДВИЖЕНИЕ В ТВОЁМ РИТМЕ</div>
            <h1>Дневник тренировок</h1>
            <p>Замечай прогресс. Сохраняй каждый подход.</p>
          </div>
          <button
            type="button"
            className="primary"
            onClick={() => openEditor("strength")}
            disabled={!effectiveDate}
          >
            ＋ Записать тренировку
          </button>
        </div>

        <WorkoutWeekCard
          diary={diary.data}
          selectedDate={effectiveDate}
          onSelectDay={(date) => changeSelectedDate(date)}
          onPrev={goToPreviousWeek}
          onNext={goToNextWeek}
          onToday={() => void goToToday()}
        />

        <WorkoutStats week={diary.data?.week} />

        <div className="training-layout">
          <div>
            <div className="workout-toolbar">
              <h2>{title}</h2>
              <div className="workout-filters" role="group" aria-label="Фильтр тренировок">
                {FILTERS.map((item) => (
                  <button
                    key={item.key}
                    type="button"
                    className={filter === item.key ? "selected" : undefined}
                    aria-pressed={filter === item.key}
                    onClick={() => setFilter(item.key)}
                  >
                    {item.label}
                  </button>
                ))}
              </div>
            </div>

            {diary.isError && !diary.data ? (
              <div className="inline-alert" role="alert">
                <span>
                  Не удалось загрузить тренировки.{" "}
                  {diary.error instanceof Error
                    ? diary.error.message
                    : "Сервер недоступен."}
                </span>
                <button
                  type="button"
                  className="text-btn"
                  onClick={() => void diary.refetch()}
                >
                  Повторить
                </button>
              </div>
            ) : diary.isLoading || !diary.data ? (
              <WorkoutListSkeleton />
            ) : dailyWorkouts.length > 0 ? (
              <div>
                {dailyWorkouts.map((workout) => (
                  <WorkoutCard
                    key={workout.id}
                    workout={workout}
                    deletePending={deletingId === workout.id}
                    onEdit={() => openEditor(workout.type, workout)}
                    onRepeat={() => openEditor(workout.type, workout, true)}
                    onDelete={() => handleDelete(workout)}
                  />
                ))}
              </div>
            ) : (
              <section className="card training-empty">
                <div className="workout-icon" aria-hidden="true">
                  <WorkoutIcon name="strength" />
                </div>
                <h3>
                  {filter === "all"
                    ? "Место для твоих достижений"
                    : "Пока нет записей этого типа"}
                </h3>
                <p>
                  Запиши тренировку, чтобы сохранить упражнения и следить за
                  своим прогрессом.
                </p>
                <button
                  type="button"
                  className="primary"
                  onClick={() =>
                    openEditor(filter === "cardio" ? "run" : "strength")
                  }
                  disabled={!effectiveDate}
                >
                  ＋ Записать тренировку
                </button>
              </section>
            )}
          </div>

          <aside className="training-aside">
            <section className="card" aria-label="Быстрый старт">
              <div className="label" style={{ marginBottom: 4 }}>
                <h2>Быстрый старт</h2>
                <span className="training-small-muted" aria-hidden="true">
                  ＋
                </span>
              </div>
              <p className="training-small-muted" style={{ lineHeight: 1.7 }}>
                Начни с готовой структуры и настрой её под себя.
              </p>
              <button
                type="button"
                className="quick-start"
                aria-label="Быстрый старт: Силовая тренировка"
                onClick={() => openEditor("strength")}
                disabled={!effectiveDate}
              >
                <span className="workout-icon" aria-hidden="true">
                  <WorkoutIcon name="strength" />
                </span>
                <span>
                  <b>Силовая тренировка</b>
                  <small>Упражнения, подходы и вес</small>
                </span>
              </button>
              <button
                type="button"
                className="quick-start"
                aria-label="Быстрый старт: Пробежка"
                onClick={() => openEditor("run")}
                disabled={!effectiveDate}
              >
                <span className="workout-icon running" aria-hidden="true">
                  <WorkoutIcon name="run" />
                </span>
                <span>
                  <b>Пробежка</b>
                  <small>Дистанция, скорость и темп</small>
                </span>
              </button>
              <button
                type="button"
                className="quick-start"
                aria-label="Быстрый старт: Другое кардио"
                onClick={() => openEditor("cardio")}
                disabled={!effectiveDate}
              >
                <span className="workout-icon running" aria-hidden="true">
                  <WorkoutIcon name="time" />
                </span>
                <span>
                  <b>Другое кардио</b>
                  <small>Велосипед, плавание и другое</small>
                </span>
              </button>
            </section>

            <section className="card" aria-label="Последние записи">
              <h2>Последние записи</h2>
              {diary.data && diary.data.recent.length > 0 ? (
                <div>
                  {diary.data.recent.map((workout) => (
                    <button
                      key={workout.id}
                      type="button"
                      className="recent-workout"
                      onClick={() => {
                        changeSelectedDate(workout.date);
                        setFilter("all");
                        scrollToTop();
                      }}
                    >
                      <span className="recent-workout-icon" aria-hidden="true">
                        <WorkoutIcon
                          name={
                            workout.type === "strength"
                              ? "strength"
                              : workout.type === "run"
                                ? "run"
                                : "time"
                          }
                        />
                      </span>
                      <span>
                        <b>{workout.name}</b>
                        <small>
                          {formatIsoDate(workout.date, {
                            day: "numeric",
                            month: "long",
                          })}{" "}
                          · {WORKOUT_TYPE_LABELS[workout.type]}
                        </small>
                      </span>
                    </button>
                  ))}
                </div>
              ) : (
                <p className="training-small-muted" style={{ marginTop: 16 }}>
                  Здесь появится история твоих тренировок.
                </p>
              )}
            </section>

            <div className="training-tip">
              <h3>✦ Сравнивай себя с собой</h3>
              <p>
                Ещё одно повторение или чуть больше времени в движении — это
                тоже прогресс.
              </p>
            </div>
          </aside>
        </div>

        <p className="training-note-bottom">
          Тренировки сохраняются на сервере. Время и калории тренировок
          учитываются в активности за день.
        </p>

        <footer className="dash-footer">
          <span>Ритм — забота о себе без спешки</span>
          <span>Полноценное приложение · данные аккаунта хранятся на сервере</span>
        </footer>
      </main>

      {editor && effectiveDate ? (
        <WorkoutEditor
          key={`${editor.mode}-${editor.type}-${editor.workout?.id ?? "new"}-${editor.nonce}`}
          state={editor}
          selectedDate={effectiveDate}
          sessionId={session.data?.id ?? null}
          onClose={() => setEditor(null)}
          onSaved={handleSaved}
        />
      ) : null}
    </>
  );
}
