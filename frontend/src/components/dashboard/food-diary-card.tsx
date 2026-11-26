import { toast } from "sonner";

import {
  useChangeFoodMealType,
  useDeleteFoodEntry,
} from "@/hooks/use-dashboard";
import {
  MEAL_TYPES,
  MEAL_TYPE_LABELS,
  type DashboardData,
  type FoodEntry,
  type FoodMealGroup,
  type MealType,
} from "@/lib/dashboard-api";
import { formatNumber } from "@/lib/date";

const MEAL_ICONS: Record<MealType, string> = {
  breakfast: "🥣",
  lunch: "🍲",
  dinner: "🍽️",
  snack: "🍎",
};

function entriesWord(count: number): string {
  const mod10 = count % 10;
  const mod100 = count % 100;

  if (mod10 === 1 && mod100 !== 11) {
    return "запись";
  }

  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
    return "записи";
  }

  return "записей";
}

function fallbackMealGroups(entries: FoodEntry[]): {
  meal_groups: FoodMealGroup[];
  uncategorized: FoodMealGroup;
} {
  const empty = (): FoodMealGroup => ({
    meal_type: null,
    entries: [],
    totals: { calories: 0, protein: 0, fat: 0, carbs: 0 },
    count: 0,
  });
  const groups = MEAL_TYPES.map((mealType) => ({
    ...empty(),
    meal_type: mealType,
  }));
  const uncategorized = empty();

  for (const entry of entries) {
    const group =
      groups.find((candidate) => candidate.meal_type === entry.meal_type) ??
      uncategorized;
    group.entries.push(entry);
    group.count += 1;
    group.totals.calories += entry.calories;
    group.totals.protein += entry.protein;
    group.totals.fat += entry.fat;
    group.totals.carbs += entry.carbs;
  }

  for (const group of [...groups, uncategorized]) {
    for (const key of ["calories", "protein", "fat", "carbs"] as const) {
      group.totals[key] = Math.round((group.totals[key] + Number.EPSILON) * 100) / 100;
    }
  }

  return { meal_groups: groups, uncategorized };
}

function groupLabel(group: FoodMealGroup): string {
  return group.meal_type ? MEAL_TYPE_LABELS[group.meal_type] : "Без категории";
}

function FoodEntryRow({
  entry,
  deletePending,
  movePending,
  onDelete,
  onMove,
}: {
  entry: FoodEntry;
  deletePending: boolean;
  movePending: boolean;
  onDelete: () => void;
  onMove: (mealType: MealType) => void;
}) {
  const icon = entry.meal_type ? MEAL_ICONS[entry.meal_type] : "🍴";

  return (
    <div className="meal">
      <div className="food-icon" aria-hidden="true">
        {icon}
      </div>
      <div className="meal-info">
        <h3>{entry.name}</h3>
        <p>
          Б {formatNumber(entry.protein)} · Ж {formatNumber(entry.fat)} · У{" "}
          {formatNumber(entry.carbs)} г
        </p>
      </div>
      <div className="meal-actions">
        <b>
          {formatNumber(entry.calories)} <small>ккал</small>
        </b>
        <label className="meal-move">
          <span className="meal-move-label">Переместить</span>
          <select
            aria-label={`Переместить «${entry.name}»`}
            value={entry.meal_type ?? ""}
            disabled={deletePending || movePending}
            onChange={(event) => onMove(event.target.value as MealType)}
          >
            {entry.meal_type === null ? (
              <option value="" disabled>
                Куда?
              </option>
            ) : null}
            {MEAL_TYPES.map((mealType) => (
              <option key={mealType} value={mealType}>
                {MEAL_TYPE_LABELS[mealType]}
              </option>
            ))}
          </select>
        </label>
        <button
          type="button"
          className="meal-remove"
          aria-label={`Удалить «${entry.name}»`}
          title="Удалить запись"
          disabled={deletePending || movePending}
          onClick={onDelete}
        >
          ×
        </button>
      </div>
    </div>
  );
}

function MealSection({
  group,
  onAdd,
  onDelete,
  onMove,
  deletePendingId,
  movePendingId,
}: {
  group: FoodMealGroup;
  onAdd?: () => void;
  onDelete: (entry: FoodEntry) => void;
  onMove: (entry: FoodEntry, mealType: MealType) => void;
  deletePendingId: string | null;
  movePendingId: string | null;
}) {
  const label = groupLabel(group);
  const titleId = `meal-section-${group.meal_type ?? "uncategorized"}`;

  return (
    <section
      className="meal-section"
      aria-labelledby={titleId}
      data-meal-type={group.meal_type ?? "uncategorized"}
    >
      <div className="meal-section-head">
        <div className="meal-section-title">
          <span aria-hidden="true">
            {group.meal_type ? MEAL_ICONS[group.meal_type] : "🍴"}
          </span>
          <h3 id={titleId}>{label}</h3>
        </div>
        <div className="meal-section-totals">
          <b>
            {formatNumber(group.totals.calories)} <small>ккал</small>
          </b>
          <span>
            Б {formatNumber(group.totals.protein)} · Ж{" "}
            {formatNumber(group.totals.fat)} · У{" "}
            {formatNumber(group.totals.carbs)}
          </span>
        </div>
        {onAdd ? (
          <button
            type="button"
            className="meal-section-add"
            aria-label={`Добавить в «${label}»`}
            onClick={onAdd}
          >
            Добавить
          </button>
        ) : null}
      </div>

      {group.entries.length === 0 ? (
        <p className="meal-section-empty">Пока пусто — добавь запись.</p>
      ) : (
        <div className="meal-section-list">
          {group.entries.map((entry) => (
            <FoodEntryRow
              key={entry.id}
              entry={entry}
              deletePending={deletePendingId === entry.id}
              movePending={movePendingId === entry.id}
              onDelete={() => onDelete(entry)}
              onMove={(mealType) => onMove(entry, mealType)}
            />
          ))}
        </div>
      )}
    </section>
  );
}

export function FoodDiaryCard({
  data,
  isLoading,
  onAddFood,
}: {
  data: DashboardData | undefined;
  isLoading: boolean;
  onAddFood: (mealType: MealType | null) => void;
}) {
  const deleteMutation = useDeleteFoodEntry();
  const moveMutation = useChangeFoodMealType();

  function handleDelete(entry: FoodEntry) {
    deleteMutation.mutate(
      { id: entry.id, date: entry.date },
      {
        onSuccess: () => toast.success("Запись удалена"),
        onError: (error) =>
          toast.error(
            error instanceof Error
              ? error.message
              : `Не удалось удалить «${entry.name}».`,
          ),
      },
    );
  }

  function handleMove(entry: FoodEntry, mealType: MealType) {
    if (mealType === entry.meal_type) {
      return;
    }

    moveMutation.mutate(
      { id: entry.id, mealType, date: entry.date },
      {
        onSuccess: () => toast.success("Запись перемещена"),
        onError: (error) =>
          toast.error(
            error instanceof Error
              ? error.message
              : `Не удалось переместить «${entry.name}».`,
          ),
      },
    );
  }

  const count = data?.food.entries.length ?? 0;
  const deletePendingId =
    deleteMutation.isPending && deleteMutation.variables
      ? deleteMutation.variables.id
      : null;
  const movePendingId =
    moveMutation.isPending && moveMutation.variables
      ? moveMutation.variables.id
      : null;
  const derived = data ? fallbackMealGroups(data.food.entries) : null;
  const mealGroups =
    data?.food.meal_groups && data.food.meal_groups.length > 0
      ? data.food.meal_groups
      : (derived?.meal_groups ?? []);
  const uncategorized = data?.food.uncategorized ?? derived?.uncategorized;

  return (
    <section className="card" id="food-diary" aria-labelledby="diary-title">
      <div className="label">
        <h2 id="diary-title">Дневник питания</h2>
        <span className="muted" aria-live="polite">
          {count} {entriesWord(count)}
        </span>
      </div>

      {isLoading || !data ? (
        <div aria-busy="true" aria-label="Загрузка дневника">
          {[0, 1, 2].map((item) => (
            <div
              key={item}
              style={{
                display: "flex",
                alignItems: "center",
                gap: 15,
                padding: "18px 0",
                borderBottom: "1px solid #edf0ed",
              }}
            >
              <span
                className="loading-line"
                style={{ width: 52, height: 52, borderRadius: 13 }}
              />
              <span style={{ flex: 1 }}>
                <span
                  className="loading-line"
                  style={{ width: "60%", height: 15 }}
                />
                <span
                  className="loading-line"
                  style={{ width: "42%", height: 11, marginTop: 8 }}
                />
              </span>
            </div>
          ))}
        </div>
      ) : (
        <div className="meal-sections">
          {mealGroups.map((group) => (
            <MealSection
              key={group.meal_type ?? "uncategorized"}
              group={group}
              onAdd={
                group.meal_type
                  ? () => onAddFood(group.meal_type as MealType)
                  : undefined
              }
              onDelete={handleDelete}
              onMove={handleMove}
              deletePendingId={deletePendingId}
              movePendingId={movePendingId}
            />
          ))}

          {uncategorized && uncategorized.count > 0 ? (
            <MealSection
              group={uncategorized}
              onDelete={handleDelete}
              onMove={handleMove}
              deletePendingId={deletePendingId}
              movePendingId={movePendingId}
            />
          ) : null}
        </div>
      )}

      <button
        type="button"
        className="add-meal"
        onClick={() => onAddFood(null)}
        disabled={!data}
      >
        ＋ Добавить приём пищи
      </button>
    </section>
  );
}
