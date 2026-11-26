import { useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import { toast } from "sonner";

import { Sidebar, type DashboardNav } from "@/components/dashboard/sidebar";
import { MobileViewSwitch } from "@/components/layout/mobile-view-switch";
import { TrainingTab } from "@/components/progress/training-tab";
import { WeightTab } from "@/components/progress/weight-tab";
import { Skeleton } from "@/components/ui/skeleton";
import { useExercises } from "@/hooks/use-exercises";
import { useLogout } from "@/hooks/use-logout";
import { useProgress } from "@/hooks/use-progress";
import { useSession } from "@/hooks/use-session";
import { formatWeekRange } from "@/lib/date";
import {
  PROGRESS_PERIOD_LABELS,
  type ProgressPeriod,
} from "@/lib/progress-api";

type ProgressTab = "weight" | "training";

const PERIODS: ProgressPeriod[] = ["month", "3months", "year", "all"];

function ProgressSkeleton() {
  return (
    <div aria-busy="true" aria-label="Загрузка прогресса">
      <div className="progress-cards">
        {[0, 1, 2].map((index) => (
          <div className="progress-card" key={index}>
            <Skeleton className="loading-line" style={{ width: "45%", height: 14 }} />
            <Skeleton
              className="loading-line"
              style={{ width: "60%", height: 30, marginTop: 16 }}
            />
            <Skeleton
              className="loading-line"
              style={{ width: "80%", height: 12, marginTop: 14 }}
            />
          </div>
        ))}
      </div>
      <div className="progress-card" style={{ marginTop: 18 }}>
        <Skeleton className="loading-line" style={{ width: "100%", height: 240 }} />
      </div>
    </div>
  );
}

export function ProgressPage() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const session = useSession();
  const logout = useLogout();
  const [period, setPeriod] = useState<ProgressPeriod>("month");
  const [tab, setTab] = useState<ProgressTab>("weight");
  const [exerciseId, setExerciseId] = useState<string | null>(null);

  const progress = useProgress(
    period,
    null,
    tab === "training" ? exerciseId : null,
  );
  const exercises = useExercises();

  const data = progress.data;
  const historyLoading =
    tab === "training" && exerciseId !== null && progress.isFetching;

  function handleNavigate(nav: DashboardNav) {
    if (nav === "today") {
      navigate("/dashboard");
      return;
    }

    if (nav === "workouts") {
      navigate("/workouts");
      return;
    }

    if (nav === "progress") {
      return;
    }

    navigate("/dashboard", { state: { focus: nav } });
  }

  async function handleLogout() {
    try {
      await logout.mutateAsync();
      queryClient.clear();
      toast.success("Вы вышли из аккаунта");
      navigate("/login", { replace: true });
    } catch {
      toast.error("Не удалось выйти. Сессия на сервере могла остаться активной.");
    }
  }

  return (
    <>
      <Sidebar
        user={session.data}
        active="progress"
        onNavigate={handleNavigate}
        onEditProfile={() => navigate("/onboarding")}
        onLogout={() => void handleLogout()}
        logoutPending={logout.isPending}
      />

      <main className="dash-main">
        <div className="mobile-logo">
          <span aria-hidden="true">∿</span> ритм
        </div>
        <MobileViewSwitch active="progress" />

        <header className="training-heading progress-heading">
          <div>
            <div className="eyebrow">ТВОЙ ПУТЬ В ЦИФРАХ</div>
            <h1>Прогресс</h1>
            <p>Замечай динамику. Опирайся на реальные данные.</p>
          </div>
          {data ? (
            <p className="progress-range" aria-label="Период прогресса">
              {formatWeekRange(data.start_date, data.end_date)}
            </p>
          ) : null}
        </header>

        {logout.isError ? (
          <div className="inline-alert" role="alert">
            <span>Не удалось выйти из аккаунта.</span>
            <button
              type="button"
              className="text-btn"
              onClick={() => void handleLogout()}
              disabled={logout.isPending}
            >
              Повторить выход
            </button>
          </div>
        ) : null}

        <div className="progress-toolbar">
          <div className="progress-tabs" role="tablist" aria-label="Разделы прогресса">
            <button
              type="button"
              role="tab"
              id="progress-tab-weight"
              aria-selected={tab === "weight"}
              aria-controls="progress-panel"
              className={tab === "weight" ? "selected" : undefined}
              onClick={() => setTab("weight")}
            >
              Вес
            </button>
            <button
              type="button"
              role="tab"
              id="progress-tab-training"
              aria-selected={tab === "training"}
              aria-controls="progress-panel"
              className={tab === "training" ? "selected" : undefined}
              onClick={() => setTab("training")}
            >
              Тренировки
            </button>
          </div>

          <div className="period-switch" role="group" aria-label="Период">
            {PERIODS.map((value) => (
              <button
                key={value}
                type="button"
                aria-pressed={period === value}
                className={period === value ? "selected" : undefined}
                onClick={() => setPeriod(value)}
              >
                {PROGRESS_PERIOD_LABELS[value]}
              </button>
            ))}
          </div>
        </div>

        <div
          id="progress-panel"
          role="tabpanel"
          aria-labelledby={
            tab === "weight" ? "progress-tab-weight" : "progress-tab-training"
          }
        >
          {progress.isError ? (
            <div className="inline-alert" role="alert">
              <span>
                Не удалось загрузить прогресс.{" "}
                {progress.error instanceof Error
                  ? progress.error.message
                  : "Сервер недоступен."}
              </span>
              <button
                type="button"
                className="text-btn"
                onClick={() => void progress.refetch()}
              >
                Повторить
              </button>
            </div>
          ) : progress.isLoading || data === undefined ? (
            <ProgressSkeleton />
          ) : tab === "weight" ? (
            <WeightTab weight={data.weight} today={data.today} />
          ) : (
            <TrainingTab
              workouts={data.workouts}
              exercises={exercises.data?.data ?? []}
              selectedExerciseId={exerciseId}
              onSelectExercise={setExerciseId}
              history={data.exercise_history}
              historyLoading={historyLoading}
            />
          )}
        </div>

        <footer className="dash-footer">
          <span>Ритм — забота о себе без спешки</span>
          <span>
            Вес и тренировки хранятся на сервере · среднее не заменяет реальные
            замеры
          </span>
        </footer>
      </main>
    </>
  );
}
