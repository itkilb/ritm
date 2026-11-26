import { act, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { toast } from "sonner";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { QueryKey } from "@tanstack/react-query";

import { ApiError } from "@/lib/api";
import { fetchProgress, type ExerciseHistory } from "@/lib/progress-api";
import {
  createWeightEntry,
  deleteWeightEntry,
  updateTargetWeight,
  updateWeightEntry,
} from "@/lib/weight-api";
import { fetchSession, type SessionUser } from "@/hooks/use-session";
import { exerciseListFixture, workoutDiaryFixture } from "@/test/workout-fixtures";
import { progressFixture } from "@/test/progress-fixtures";
import { renderApp } from "@/test/render-app";

const completedUser: SessionUser = {
  id: 2,
  email: "completed@example.com",
  name: null,
  profile_completed: true,
  profile: {
    weight_kg: 79.5,
    height_cm: 180,
    birth_date: "1990-01-15",
    fat_percentage: null,
    gender: "male",
    activity_level: "moderate",
    goal: "lose_weight",
    timezone: "Europe/Moscow",
  },
};

vi.mock("@/hooks/use-session", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/hooks/use-session")>();
  return { ...actual, fetchSession: vi.fn() };
});

vi.mock("@/lib/progress-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/progress-api")>();
  return { ...actual, fetchProgress: vi.fn() };
});

vi.mock("@/lib/weight-api", async (importOriginal) => {
  const actual = await importOriginal<typeof import("@/lib/weight-api")>();
  return {
    ...actual,
    createWeightEntry: vi.fn(),
    updateWeightEntry: vi.fn(),
    deleteWeightEntry: vi.fn(),
    updateTargetWeight: vi.fn(),
  };
});

vi.mock("sonner", () => ({
  toast: { success: vi.fn(), error: vi.fn() },
}));

const monthKey = ["progress", "month", "today", null] as const;

function exerciseHistory(
  measurementType: ExerciseHistory["exercise"]["measurement_type"],
  points: ExerciseHistory["points"],
): ExerciseHistory {
  const weights = points
    .map((point) => point.weight_kg)
    .filter((value): value is number => value !== null);
  const reps = points
    .map((point) => point.reps)
    .filter((value): value is number => value !== null);
  const seconds = points
    .map((point) => point.seconds)
    .filter((value): value is number => value !== null);

  return {
    exercise: {
      id: "catalog-bench",
      name: "Жим лёжа",
      muscle_group: "Грудь",
      equipment: "Штанга",
      measurement_type: measurementType,
    },
    points,
    summary: {
      count: points.length,
      best_weight_kg: weights.length > 0 ? Math.max(...weights) : null,
      best_reps: reps.length > 0 ? Math.max(...reps) : null,
      best_seconds: seconds.length > 0 ? Math.max(...seconds) : null,
    },
  };
}

function renderProgress(
  overrides: Parameters<typeof progressFixture>[0] = {},
  queries: Array<[QueryKey, unknown]> = [],
) {
  return renderApp({
    user: completedUser,
    route: "/progress",
    extraQueries: [
      [monthKey, progressFixture(overrides)],
      [["exercises"], exerciseListFixture()],
      ...queries,
    ],
    seedDashboard: false,
  });
}

describe("progress screen", () => {
  beforeEach(() => {
    vi.mocked(fetchSession).mockResolvedValue(completedUser);
    vi.mocked(fetchProgress).mockImplementation(async (period) =>
      progressFixture(
        period === "month"
          ? {}
          : {
              period,
              start_date: period === "year" ? "2025-01-01" : "2024-11-01",
            },
      ),
    );
    vi.mocked(createWeightEntry).mockResolvedValue({
      id: "weight-new",
      date: "2025-01-14",
      weight_kg: 79.1,
    });
    vi.mocked(updateWeightEntry).mockResolvedValue({
      id: "weight-1",
      date: "2025-01-01",
      weight_kg: 81,
    });
    vi.mocked(deleteWeightEntry).mockResolvedValue(undefined);
    vi.mocked(updateTargetWeight).mockResolvedValue(74);
  });

  it("renders actual weight, period change, target and a real-point chart", async () => {
    renderProgress();

    expect(
      await screen.findByRole("heading", { name: "Прогресс" }),
    ).toBeInTheDocument();
    expect(screen.getByText("Текущий вес")).toBeInTheDocument();
    expect(screen.getAllByText("79,5 кг").length).toBeGreaterThan(0);
    expect(screen.getByText("−0,5 кг")).toBeInTheDocument();
    expect(screen.getByText("75 кг")).toBeInTheDocument();
    expect(screen.getByRole("img", { name: "График веса" })).toBeInTheDocument();
    expect(
      screen.getByText(/пропущенные дни не интерполируются/i),
    ).toBeInTheDocument();
    expect(screen.getByLabelText("Список замеров веса")).toBeInTheDocument();

    const list = screen.getByLabelText("Список замеров веса");
    expect(within(list).getByText("2025-01-01")).toBeInTheDocument();
    expect(within(list).getByText("80 кг")).toBeInTheDocument();
  });

  it("shows a single 7-day average marker and keeps units out of the axis ticks", async () => {
    renderProgress();

    await screen.findByText("Текущий вес");

    const chart = screen.getByRole("img", { name: "График веса" });
    const figure = chart.closest("figure");

    expect(figure).not.toBeNull();

    const container = figure as HTMLElement;
    expect(within(container).getByText("Фактический вес, кг")).toBeInTheDocument();
    expect(
      within(container).getByText("Среднее за 7 дней, кг"),
    ).toBeInTheDocument();

    const markers = chart.querySelectorAll("circle.chart-average-point");
    expect(markers).toHaveLength(1);
    expect(markers[0]?.querySelector("title")).toHaveTextContent(
      "2025-01-15: 79,75 кг",
    );
    expect(container.querySelector(".chart-average-line")).toBeNull();

    const axisValues = Array.from(
      chart.querySelectorAll("text.chart-axis-value"),
    );
    expect(axisValues).toHaveLength(3);

    for (const label of axisValues) {
      expect(label.textContent).toMatch(/^-?\d+(,\d)?$/);
    }

    expect(
      Array.from(chart.querySelectorAll("text")).some(
        (node) => node.textContent?.trim() === "кг",
      ),
    ).toBe(false);
  });

  it("fits both SVG charts to a narrow container and keeps their labels readable", async () => {
    const user = userEvent.setup();
    const callbacks: ResizeObserverCallback[] = [];

    class ResizeObserverStub {
      private callback: ResizeObserverCallback;

      constructor(callback: ResizeObserverCallback) {
        this.callback = callback;
        callbacks.push(callback);
      }

      observe() {
        this.callback(
          [{ contentRect: { width: 300 } } as ResizeObserverEntry],
          this as unknown as ResizeObserver,
        );
      }

      unobserve() {}
      disconnect() {}
    }

    vi.stubGlobal("ResizeObserver", ResizeObserverStub);

    try {
      const history = exerciseHistory("weighted", [
        {
          date: "2025-01-10",
          workout_id: "workout-1",
          workout_name: "Верх тела",
          exercise_name: "Жим лёжа",
          mode: "weighted",
          weight_kg: 70,
          reps: 8,
          seconds: null,
        },
      ]);

      vi.mocked(fetchProgress).mockResolvedValue(
        progressFixture({ exercise_history: history }),
      );
      renderProgress({ exercise_history: history });

      const weightChart = await screen.findByRole("img", {
        name: "График веса",
      });
      await waitFor(() => {
        expect(weightChart).toHaveAttribute("viewBox", "0 0 300 210");
      });

      const weightLabels = Array.from(
        weightChart.querySelectorAll("svg text"),
      ).map((node) => node.textContent?.trim());
      expect(weightLabels.filter((label) => /янв/.test(label ?? ""))).toHaveLength(
        2,
      );
      expect(
        weightLabels.filter((label) => /^-?\d+(,\d)?$/.test(label ?? "")),
      ).toHaveLength(3);

      act(() => {
        callbacks.at(-1)?.(
          [{ contentRect: { width: 640 } } as ResizeObserverEntry],
          {} as ResizeObserver,
        );
      });

      await waitFor(() => {
        expect(weightChart).toHaveAttribute("viewBox", "0 0 640 250");
      });

      await user.click(screen.getByRole("tab", { name: "Тренировки" }));
      await user.selectOptions(
        screen.getByLabelText("Упражнение для истории"),
        "catalog-bench",
      );

      const exerciseChart = await screen.findByRole("group", {
        name: /Точки истории упражнения/,
      });
      await waitFor(() => {
        expect(exerciseChart).toHaveAttribute("viewBox", "0 0 300 210");
      });
      expect(
        exerciseChart.querySelectorAll("text.chart-axis-text").length,
      ).toBe(4);
    } finally {
      vi.unstubAllGlobals();
    }
  });

  it("switches weight, three months, year and all-time periods with server queries", async () => {
    const user = userEvent.setup();
    renderProgress();

    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("button", { name: "3 месяца" }));

    await waitFor(() => {
      expect(fetchProgress).toHaveBeenCalledWith("3months", null, null);
    });

    await user.click(screen.getByRole("button", { name: "Год" }));
    await waitFor(() => {
      expect(fetchProgress).toHaveBeenCalledWith("year", null, null);
    });

    await user.click(screen.getByRole("button", { name: "Всё время" }));
    await waitFor(() => {
      expect(fetchProgress).toHaveBeenCalledWith("all", null, null);
    });
  });

  it("does not let a late period response replace a newer selection", async () => {
    const user = userEvent.setup();
    let resolveThree!: (value: ReturnType<typeof progressFixture>) => void;
    const three = new Promise<ReturnType<typeof progressFixture>>((resolve) => {
      resolveThree = resolve;
    });

    vi.mocked(fetchProgress).mockImplementation((period) => {
      if (period === "3months") {
        return three;
      }

      return Promise.resolve(
        progressFixture({ period: "year", start_date: "2025-01-01" }),
      );
    });

    renderProgress();
    await screen.findByText("Текущий вес");

    await user.click(screen.getByRole("button", { name: "3 месяца" }));
    await user.click(screen.getByRole("button", { name: "Год" }));

    await waitFor(() => {
      expect(fetchProgress).toHaveBeenCalledWith("year", null, null);
    });

    resolveThree(
      progressFixture({ period: "3months", start_date: "2024-11-01" }),
    );
    await waitFor(() => {
      expect(screen.getByLabelText("Период прогресса")).toHaveTextContent(
        "1–15 января 2025",
      );
    });
  });

  it("shows weekly training buckets including zero weeks and keeps cardio kinds separate", async () => {
    const user = userEvent.setup();
    renderProgress();

    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));

    expect(screen.getByText("Тренировок за период")).toBeInTheDocument();
    expect(screen.getByText("Минут движения")).toBeInTheDocument();
    expect(screen.getByText("Км бега")).toBeInTheDocument();
    expect(screen.getByLabelText("Тренировки по неделям")).toBeInTheDocument();
    expect(screen.getByText("0 · 0 мин")).toBeInTheDocument();
    expect(screen.getByText("6:00 мин/км")).toBeInTheDocument();
    expect(screen.getByText("13 км/ч")).toBeInTheDocument();
    expect(screen.getByText("Бег")).toBeInTheDocument();
    expect(screen.getByText("Другое кардио")).toBeInTheDocument();
  });

  it("loads selected exercise history and links each point to its workout", async () => {
    const user = userEvent.setup();
    const history = {
      exercise: {
        id: "catalog-bench",
        name: "Жим лёжа",
        muscle_group: "Грудь",
        equipment: "Штанга",
        measurement_type: "weighted" as const,
      },
      points: [
        {
          date: "2025-01-10",
          workout_id: "workout-1",
          workout_name: "Верх тела",
          exercise_name: "Жим лёжа",
          mode: "weighted" as const,
          weight_kg: 70,
          reps: 8,
          seconds: null,
        },
      ],
      summary: {
        count: 1,
        best_weight_kg: 70,
        best_reps: 8,
        best_seconds: null,
      },
    };

    vi.mocked(fetchProgress).mockResolvedValue(
      progressFixture({ exercise_history: history }),
    );

    renderProgress();
    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));

    await user.selectOptions(
      screen.getByLabelText("Упражнение для истории"),
      "catalog-bench",
    );

    await waitFor(() => {
      expect(fetchProgress).toHaveBeenCalledWith(
        "month",
        null,
        "catalog-bench",
      );
    });

    await waitFor(() => {
      expect(screen.getAllByText("70 кг × 8").length).toBeGreaterThan(0);
    });
    expect(
      screen.getByRole("link", { name: "Открыть тренировку" }),
    ).toHaveAttribute("href", "/workouts?workout=workout-1");

    const chart = screen.getByRole("figure", {
      name: "График истории упражнения Жим лёжа",
    });
    expect(within(chart).getByRole("link")).toHaveAttribute(
      "href",
      "/workouts?workout=workout-1",
    );
    expect(within(chart).getByRole("link")).toHaveAccessibleName(
      /2025-01-10: 70 кг/,
    );
    expect(chart.querySelectorAll("circle")).toHaveLength(1);
    expect(chart.querySelector("figcaption")?.textContent).toContain("· кг ·");
    expect(
      Array.from(chart.querySelectorAll("svg text")).map((node) =>
        node.textContent?.trim(),
      ),
    ).not.toContain("кг");
  });

  it("keeps every workout point on the same day and renders a real zero", async () => {
    const user = userEvent.setup();
    const history = exerciseHistory("weighted", [
      {
        date: "2025-01-10",
        workout_id: "workout-1",
        workout_name: "Верх тела",
        exercise_name: "Жим лёжа",
        mode: "weighted",
        weight_kg: 0,
        reps: 12,
        seconds: null,
      },
      {
        date: "2025-01-10",
        workout_id: "workout-2",
        workout_name: "Вечерняя",
        exercise_name: "Жим лёжа",
        mode: "weighted",
        weight_kg: 70,
        reps: 8,
        seconds: null,
      },
      {
        date: "2025-01-12",
        workout_id: "workout-3",
        workout_name: "Силовая",
        exercise_name: "Жим лёжа",
        mode: "weighted",
        weight_kg: 80,
        reps: 5,
        seconds: null,
      },
    ]);

    vi.mocked(fetchProgress).mockResolvedValue(
      progressFixture({ exercise_history: history }),
    );

    renderProgress();
    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));
    await user.selectOptions(
      screen.getByLabelText("Упражнение для истории"),
      "catalog-bench",
    );

    const chart = await screen.findByRole("figure", {
      name: "График истории упражнения Жим лёжа",
    });
    const chartLinks = within(chart).getAllByRole("link");
    expect(chartLinks).toHaveLength(3);
    expect(chartLinks[0]).toHaveAccessibleName("2025-01-10: 0 кг. Верх тела. Открыть тренировку");
    expect(chartLinks[0]).toHaveAttribute("href", "/workouts?workout=workout-1");
    expect(chartLinks[1]).toHaveAccessibleName("2025-01-10: 70 кг. Вечерняя. Открыть тренировку");
    expect(chart.querySelectorAll("circle")).toHaveLength(3);
    expect(screen.getByText(/Совпадения по дате: 1/)).toBeInTheDocument();
  });

  it("shows an empty history state without an empty chart frame", async () => {
    const user = userEvent.setup();
    vi.mocked(fetchProgress).mockResolvedValue(
      progressFixture({ exercise_history: exerciseHistory("weighted", []) }),
    );

    renderProgress();
    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));
    await user.selectOptions(
      screen.getByLabelText("Упражнение для истории"),
      "catalog-bench",
    );

    expect(
      await screen.findByText("Пока нет связанных записей"),
    ).toBeInTheDocument();
    expect(screen.queryByRole("figure")).not.toBeInTheDocument();
  });

  it.each([
    {
      type: "reps" as const,
      unit: "повторения",
      value: "12 повторений",
      point: {
        date: "2025-01-10",
        workout_id: "workout-1",
        workout_name: "Верх тела",
        exercise_name: "Отжимания",
        mode: "reps" as const,
        weight_kg: null,
        reps: 12,
        seconds: null,
      },
    },
    {
      type: "time" as const,
      unit: "секунды",
      value: "75 сек",
      point: {
        date: "2025-01-10",
        workout_id: "workout-1",
        workout_name: "Верх тела",
        exercise_name: "Планка",
        mode: "time" as const,
        weight_kg: null,
        reps: null,
        seconds: 75,
      },
    },
  ])("charts $type as its own metric", async ({ type, unit, value, point }) => {
    const user = userEvent.setup();
    vi.mocked(fetchProgress).mockResolvedValue(
      progressFixture({ exercise_history: exerciseHistory(type, [point]) }),
    );

    renderProgress();
    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));
    await user.selectOptions(
      screen.getByLabelText("Упражнение для истории"),
      "catalog-bench",
    );

    const chart = await screen.findByRole("figure", {
      name: "График истории упражнения Жим лёжа",
    });
    expect(chart.querySelector("figcaption")?.textContent).toContain(
      `· ${unit} ·`,
    );
    expect(
      Array.from(chart.querySelectorAll("svg text")).map((node) =>
        node.textContent?.trim(),
      ),
    ).not.toContain(unit);
    expect(within(chart).getByRole("link")).toHaveAccessibleName(
      new RegExp(`2025-01-10: ${value}`),
    );
  });

  it("toggles weekly bars between workout count and minutes", async () => {
    const user = userEvent.setup();
    renderProgress({
      workouts: {
        ...progressFixture().workouts,
        weeks: [
          {
            week_start: "2024-12-30",
            week_end: "2025-01-05",
            count: 1,
            minutes: 10,
            calories: 0,
            distance_km: 0,
          },
          {
            week_start: "2025-01-06",
            week_end: "2025-01-12",
            count: 2,
            minutes: 90,
            calories: 0,
            distance_km: 0,
          },
          {
            week_start: "2025-01-13",
            week_end: "2025-01-19",
            count: 4,
            minutes: 30,
            calories: 0,
            distance_km: 0,
          },
        ],
      },
    });

    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));

    expect(
      screen.getByRole("button", { name: "Количество" }),
    ).toHaveAttribute("aria-pressed", "true");
    expect(
      screen.getByTestId("week-bar-2025-01-06").firstElementChild,
    ).toHaveStyle({ width: "50%" });

    await user.click(screen.getByRole("button", { name: "Минуты" }));

    expect(
      screen.getByRole("button", { name: "Минуты" }),
    ).toHaveAttribute("aria-pressed", "true");
    expect(
      screen.getByTestId("week-bar-2025-01-06").firstElementChild,
    ).toHaveStyle({ width: "100%" });
    expect(
      screen.getByTestId("week-bar-2025-01-13").firstElementChild,
    ).toHaveStyle({ width: "33%" });
    expect(screen.getByText("90 мин · 2")).toBeInTheDocument();
  });

  it("shows separate cardio kind cards without a combined speed", async () => {
    const user = userEvent.setup();
    renderProgress({
      workouts: {
        ...progressFixture().workouts,
        cardio: {
          count: 4,
          minutes: 120,
          distance_km: 35,
          speed_kmh: null,
          kinds: [
            {
              activity_kind: "outdoor_bike",
              count: 1,
              minutes: 30,
              distance_km: 12,
              speed_kmh: 24,
            },
            {
              activity_kind: "stationary_bike",
              count: 1,
              minutes: 30,
              distance_km: 20,
              speed_kmh: 40,
            },
            {
              activity_kind: "other",
              count: 1,
              minutes: 30,
              distance_km: 2,
              speed_kmh: null,
            },
            {
              activity_kind: "unknown",
              count: 1,
              minutes: 30,
              distance_km: 1,
              speed_kmh: null,
            },
          ],
        },
      },
    });

    await screen.findByText("Текущий вес");
    await user.click(screen.getByRole("tab", { name: "Тренировки" }));

    expect(screen.getByRole("heading", { name: "Велосипед на улице" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Велотренажёр" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Другое" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Вид не указан" })).toBeInTheDocument();
    expect(screen.getByText("24 км/ч")).toBeInTheDocument();
    expect(screen.getByText("40 км/ч")).toBeInTheDocument();
    expect(screen.getAllByText("Скорость")).toHaveLength(2);
    expect(screen.queryByText("13 км/ч")).not.toBeInTheDocument();
  });

  it("adds a measurement, keeps server 422 errors in the form and preserves input", async () => {
    const user = userEvent.setup();
    renderProgress();
    await screen.findByText("Текущий вес");

    await user.click(screen.getByRole("button", { name: "＋ Добавить замер" }));
    const dialog = await screen.findByRole("dialog", { name: "Добавить замер" });
    const weight = within(dialog).getByLabelText("Вес, кг");
    await user.type(weight, "79,1");
    await user.click(
      within(dialog).getByRole("button", { name: "Сохранить замер" }),
    );

    await waitFor(() => {
      expect(createWeightEntry).toHaveBeenCalledWith({
        date: "2025-01-15",
        weight_kg: 79.1,
      });
    });

    await waitFor(() => {
      expect(
        screen.queryByRole("dialog", { name: "Добавить замер" }),
      ).not.toBeInTheDocument();
    });

    await user.click(screen.getByRole("button", { name: "＋ Добавить замер" }));
    const duplicateDialog = await screen.findByRole("dialog", {
      name: "Добавить замер",
    });
    await user.type(
      within(duplicateDialog).getByLabelText("Вес, кг"),
      "80,2",
    );
    vi.mocked(createWeightEntry).mockRejectedValueOnce(
      new ApiError(422, "Проверьте поля.", {
        message: "Проверьте поля.",
        errors: { date: "Замер на эту дату уже есть." },
      }),
    );
    await user.click(
      within(duplicateDialog).getByRole("button", { name: "Сохранить замер" }),
    );

    await waitFor(() => {
      expect(
        within(duplicateDialog).getByText("Замер на эту дату уже есть."),
      ).toBeInTheDocument();
    });
    expect(
      within(duplicateDialog).getByLabelText("Вес, кг"),
    ).toHaveValue("80,2");
  });

  it("edits and deletes measurements and edits the optional target weight", async () => {
    const user = userEvent.setup();
    renderProgress();
    await screen.findByText("Текущий вес");

    await user.click(screen.getByRole("button", { name: "Изменить замер 2025-01-01" }));
    const editDialog = await screen.findByRole("dialog", { name: "Изменить замер" });
    const weight = within(editDialog).getByLabelText("Вес, кг");
    await user.clear(weight);
    await user.type(weight, "81");
    await user.click(
      within(editDialog).getByRole("button", { name: "Сохранить замер" }),
    );

    await waitFor(() => {
      expect(updateWeightEntry).toHaveBeenCalledWith("weight-1", {
        date: "2025-01-01",
        weight_kg: 81,
      });
    });

    await user.click(screen.getByRole("button", { name: "Удалить замер 2025-01-15" }));
    await waitFor(() => {
      expect(deleteWeightEntry).toHaveBeenCalledWith("weight-2");
    });

    await user.click(screen.getByRole("button", { name: "Изменить цель" }));
    const targetDialog = await screen.findByRole("dialog", { name: "Целевой вес" });
    const target = within(targetDialog).getByLabelText("Целевой вес, кг");
    await user.clear(target);
    await user.type(target, "74");
    await user.click(
      within(targetDialog).getByRole("button", { name: "Сохранить" }),
    );

    await waitFor(() => {
      expect(updateTargetWeight).toHaveBeenCalledWith(74);
    });
    expect(toast.success).toHaveBeenCalled();
  });

  it("shows an honest empty journal state without a fabricated zero", async () => {
    renderProgress({
      weight: {
        entries: [],
        average: [],
        latest: null,
        change_kg: null,
        profile_weight_kg: 79.5,
        target_weight_kg: null,
      },
    });

    expect(await screen.findByText("Журнал веса пока пуст")).toBeInTheDocument();
    expect(screen.getByText(/не показывается как исторический замер/)).toBeInTheDocument();
    expect(screen.getByText("Не задан")).toBeInTheDocument();
    expect(screen.queryByText("0 кг")).not.toBeInTheDocument();
  });

  it("shows a retryable error and recovers", async () => {
    const user = userEvent.setup();
    vi.mocked(fetchProgress).mockRejectedValueOnce(
      new ApiError(500, "Сервер недоступен."),
    );

    renderApp({
      user: completedUser,
      route: "/progress",
      extraQueries: [[["exercises"], exerciseListFixture()]],
      seedDashboard: false,
    });

    expect(
      await screen.findByText(/Не удалось загрузить прогресс/),
    ).toBeInTheDocument();

    vi.mocked(fetchProgress).mockResolvedValue(progressFixture());
    await user.click(screen.getByRole("button", { name: "Повторить" }));

    expect(await screen.findByText("Текущий вес")).toBeInTheDocument();
  });

  it("is reachable from the workouts mobile navigation", async () => {
    const user = userEvent.setup();
    renderApp({
      user: completedUser,
      route: "/workouts",
      extraQueries: [
        [["workouts", "today"], workoutDiaryFixture()],
        [monthKey, progressFixture()],
        [["exercises"], exerciseListFixture()],
      ],
    });

    await user.click(
      screen.getByRole("button", { name: "Прогресс — мобильная навигация" }),
    );

    expect(
      await screen.findByRole("heading", { name: "Прогресс" }),
    ).toBeInTheDocument();
  });
});
