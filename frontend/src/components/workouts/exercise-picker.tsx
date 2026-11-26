import { useId, useMemo, useState, type ReactNode } from "react";
import type { UseFormReturn } from "react-hook-form";

import { useCreateExercise, useExercises } from "@/hooks/use-exercises";
import { ApiError, validationErrors } from "@/lib/api";
import {
  EQUIPMENT,
  EXERCISE_MEASUREMENT_LABELS,
  MUSCLE_GROUPS,
  isMeasurementCompatible,
  type Exercise,
} from "@/lib/exercise-api";
import type { ExerciseMode } from "@/lib/workout-api";
import {
  emptySet,
  type WorkoutFormValues,
} from "@/lib/workout-form";

function isDefaultSet(values: {
  weight_kg: string;
  reps: string;
  seconds: string;
}): boolean {
  return (
    (values.weight_kg === "" || values.weight_kg === "0") &&
    (values.reps === "" || values.reps === "10") &&
    (values.seconds === "" || values.seconds === "30")
  );
}

export function ExercisePicker({
  form,
  index,
  busy,
}: {
  form: UseFormReturn<WorkoutFormValues>;
  index: number;
  busy: boolean;
}) {
  const base = `exercises.${index}` as const;
  const listId = useId();
  const inputId = useId();
  const exercises = useExercises();
  const createMutation = useCreateExercise();

  const name = form.watch(`${base}.name`);
  const exerciseId = form.watch(`${base}.exercise_id`);
  const catalogOptional = form.watch(`${base}.catalog_optional`);

  const [query, setQuery] = useState(name);
  const [open, setOpen] = useState(false);
  const [highlight, setHighlight] = useState(0);
  const [muscle, setMuscle] = useState("");
  const [equipment, setEquipment] = useState("");
  const [customOpen, setCustomOpen] = useState(false);
  const [customName, setCustomName] = useState("");
  const [customGroup, setCustomGroup] = useState<string>(MUSCLE_GROUPS[0]);
  const [customEquipment, setCustomEquipment] = useState<string>(EQUIPMENT[0]);
  const [customType, setCustomType] = useState<ExerciseMode>("weighted");
  const [customError, setCustomError] = useState<string | null>(null);

  const all = exercises.data?.data ?? [];
  const filters = exercises.data?.meta.filters;
  const muscleGroups = filters?.muscle_groups ?? [...MUSCLE_GROUPS];
  const equipmentOptions = filters?.equipment ?? [...EQUIPMENT];

  const filtered = useMemo(() => {
    const term = query.trim().toLocaleLowerCase("ru");

    return all
      .filter((exercise) => {
        if (muscle && exercise.muscle_group !== muscle) {
          return false;
        }

        if (equipment && exercise.equipment !== equipment) {
          return false;
        }

        return term === "" || exercise.name.toLocaleLowerCase("ru").includes(term);
      })
      .slice(0, 50);
  }, [all, query, muscle, equipment]);

  const selected = all.find((exercise) => exercise.id === exerciseId) ?? null;
  const nameError = form.formState.errors.exercises?.[index]?.name?.message;
  const idError =
    form.formState.errors.exercises?.[index]?.exercise_id?.message;
  const modeError = form.formState.errors.exercises?.[index]?.mode?.message;

  function errorText(message: string | undefined): ReactNode {
    return message ? <p className="field-error">{message}</p> : null;
  }

  function canReplaceSafely(): boolean {
    const sets = form.getValues(`${base}.sets`);

    return (
      exerciseId === null &&
      sets.length <= 1 &&
      sets.every((set) => isDefaultSet(set))
    );
  }

  function applySelection(exercise: Exercise): void {
    const currentMode = form.getValues(`${base}.mode`);

    if (!isMeasurementCompatible(exercise.measurement_type, currentMode)) {
      if (!canReplaceSafely()) {
        form.setError(`${base}.mode`, {
          type: "manual",
          message:
            "Способ учёта не совпадает с этим упражнением. Сначала измените способ учёта — подходы не будут потеряны.",
        });
        setQuery(form.getValues(`${base}.name`));
        return;
      }

      form.setValue(`${base}.mode`, exercise.measurement_type, {
        shouldDirty: true,
        shouldValidate: false,
      });
      form.setValue(`${base}.sets`, [emptySet(exercise.measurement_type)], {
        shouldDirty: true,
        shouldValidate: false,
      });
    }

    form.setValue(`${base}.name`, exercise.name, {
      shouldDirty: true,
      shouldValidate: true,
    });
    form.setValue(`${base}.exercise_id`, exercise.id, {
      shouldDirty: true,
      shouldValidate: true,
    });
    form.setValue(`${base}.catalog_optional`, false, {
      shouldDirty: true,
      shouldValidate: false,
    });
    form.clearErrors([
      `${base}.name`,
      `${base}.exercise_id`,
      `${base}.mode`,
    ]);
    setQuery(exercise.name);
    setOpen(false);
    setHighlight(0);
  }

  function handleInput(value: string): void {
    setQuery(value);
    setOpen(true);
    setHighlight(0);
  }

  function moveHighlight(delta: number): void {
    if (filtered.length === 0) {
      return;
    }

    setOpen(true);
    setHighlight((current) => {
      const next = current + delta;

      if (next < 0) {
        return filtered.length - 1;
      }

      if (next >= filtered.length) {
        return 0;
      }

      return next;
    });
  }

  function applyFirst(): void {
    const exercise = filtered[highlight] ?? filtered[0];

    if (exercise) {
      applySelection(exercise);
    }
  }

  async function createCustom(): Promise<void> {
    setCustomError(null);
    const trimmed = customName.trim();

    if (trimmed === "" || trimmed.length > 100) {
      setCustomError("Название: от 1 до 100 символов.");
      return;
    }

    try {
      const created = await createMutation.mutateAsync({
        name: trimmed,
        muscle_group: customGroup,
        equipment: customEquipment,
        measurement_type: customType,
      });

      setCustomOpen(false);
      setCustomName("");
      applySelection(created);
    } catch (error) {
      if (error instanceof ApiError) {
        const fields = validationErrors(error.payload);
        setCustomError(
          fields.name ??
            fields.muscle_group ??
            fields.equipment ??
            fields.measurement_type ??
            error.message,
        );
      } else {
        setCustomError("Не удалось создать упражнение. Попробуйте снова.");
      }
    }
  }

  return (
    <div className="exercise-picker">
      <div className="exercise-title-row">
        <div className="exercise-picker-input-wrap">
          <label className="exercise-name-label" htmlFor={inputId}>
            <span className="sr-only">Название упражнения {index + 1}</span>
            <input
              id={inputId}
              type="text"
              className="exercise-name"
              placeholder="Найти упражнение"
              autoComplete="off"
              maxLength={100}
              role="combobox"
              aria-autocomplete="list"
              aria-expanded={open}
              aria-controls={listId}
              aria-activedescendant={
                open && filtered[highlight]
                  ? `${listId}-${filtered[highlight].id}`
                  : undefined
              }
              aria-invalid={Boolean(nameError || idError)}
              disabled={busy}
              value={query}
              onChange={(event) => handleInput(event.target.value)}
              onFocus={() => setOpen(true)}
              onKeyDown={(event) => {
                if (event.key === "ArrowDown") {
                  event.preventDefault();
                  moveHighlight(1);
                } else if (event.key === "ArrowUp") {
                  event.preventDefault();
                  moveHighlight(-1);
                } else if (event.key === "Enter") {
                  if (open) {
                    event.preventDefault();
                    applyFirst();
                  }
                } else if (event.key === "Escape") {
                  setOpen(false);
                }
              }}
            />
          </label>

          {open ? (
            <div className="exercise-picker-panel">
              <div className="exercise-filters">
                <label>
                  <span className="sr-only">
                    Группа мышц упражнения {index + 1}
                  </span>
                  <select
                    aria-label={`Группа мышц упражнения ${index + 1}`}
                    value={muscle}
                    onChange={(event) => setMuscle(event.target.value)}
                  >
                    <option value="">Все мышцы</option>
                    {muscleGroups.map((group) => (
                      <option key={group} value={group}>
                        {group}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span className="sr-only">
                    Оборудование упражнения {index + 1}
                  </span>
                  <select
                    aria-label={`Оборудование упражнения ${index + 1}`}
                    value={equipment}
                    onChange={(event) => setEquipment(event.target.value)}
                  >
                    <option value="">Любое оборудование</option>
                    {equipmentOptions.map((item) => (
                      <option key={item} value={item}>
                        {item}
                      </option>
                    ))}
                  </select>
                </label>
              </div>

              {exercises.isLoading ? (
                <p className="exercise-picker-status">Загружаем справочник…</p>
              ) : exercises.isError ? (
                <div className="exercise-picker-status" role="alert">
                  <span>Не удалось загрузить справочник.</span>
                  <button
                    type="button"
                    className="text-btn"
                    onClick={() => void exercises.refetch()}
                  >
                    Повторить
                  </button>
                </div>
              ) : (
                <ul
                  className="exercise-options"
                  id={listId}
                  role="listbox"
                  aria-label={`Упражнения для упражнения ${index + 1}`}
                >
                  {filtered.map((exercise, optionIndex) => (
                    <li key={exercise.id}>
                      <button
                        type="button"
                        id={`${listId}-${exercise.id}`}
                        role="option"
                        aria-selected={exercise.id === exerciseId}
                        className={
                          optionIndex === highlight ? "highlighted" : undefined
                        }
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => applySelection(exercise)}
                      >
                        <b>{exercise.name}</b>
                        <span>
                          {exercise.muscle_group} · {exercise.equipment} ·{" "}
                          {EXERCISE_MEASUREMENT_LABELS[exercise.measurement_type]}
                          {exercise.is_custom ? " · личное" : ""}
                        </span>
                      </button>
                    </li>
                  ))}
                  {filtered.length === 0 ? (
                    <li className="exercise-picker-status">
                      Ничего не найдено. Создайте личное упражнение ниже.
                    </li>
                  ) : null}
                </ul>
              )}
            </div>
          ) : null}
        </div>
      </div>

      {errorText(nameError)}
      {errorText(idError)}
      {errorText(modeError)}

      <div className="exercise-picker-meta">
        {selected ? (
          <span className="exercise-linked">
            Справочник: {selected.name} ·{" "}
            {EXERCISE_MEASUREMENT_LABELS[selected.measurement_type]}
          </span>
        ) : catalogOptional ? (
          <span className="exercise-legacy">
            Свободное название сохранено. Выберите упражнение из справочника,
            чтобы связать запись.
          </span>
        ) : (
          <span className="exercise-legacy">
            Выберите упражнение из списка или создайте личное.
          </span>
        )}
      </div>

      <button
        type="button"
        className="text-btn exercise-create-toggle"
        onClick={() => setCustomOpen((value) => !value)}
        disabled={busy}
        aria-expanded={customOpen}
      >
        {customOpen ? "Скрыть создание" : "＋ Создать личное упражнение"}
      </button>

      {customOpen ? (
        <div className="exercise-create" aria-label={`Новое упражнение ${index + 1}`}>
          <label>
            Название
            <input
              type="text"
              maxLength={100}
              value={customName}
              disabled={createMutation.isPending}
              onChange={(event) => setCustomName(event.target.value)}
            />
          </label>
          <div className="exercise-create-grid">
            <label>
              Группа мышц
              <select
                value={customGroup}
                disabled={createMutation.isPending}
                onChange={(event) => setCustomGroup(event.target.value)}
              >
                {muscleGroups.map((group) => (
                  <option key={group} value={group}>
                    {group}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Оборудование
              <select
                value={customEquipment}
                disabled={createMutation.isPending}
                onChange={(event) => setCustomEquipment(event.target.value)}
              >
                {equipmentOptions.map((item) => (
                  <option key={item} value={item}>
                    {item}
                  </option>
                ))}
              </select>
            </label>
            <label>
              Способ учёта
              <select
                value={customType}
                disabled={createMutation.isPending}
                onChange={(event) =>
                  setCustomType(event.target.value as ExerciseMode)
                }
              >
                {(Object.keys(EXERCISE_MEASUREMENT_LABELS) as ExerciseMode[]).map(
                  (value) => (
                    <option key={value} value={value}>
                      {EXERCISE_MEASUREMENT_LABELS[value]}
                    </option>
                  ),
                )}
              </select>
            </label>
          </div>
          {customError ? (
            <p className="field-error" role="alert">
              {customError}
            </p>
          ) : null}
          <button
            type="button"
            className="secondary"
            onClick={() => void createCustom()}
            disabled={createMutation.isPending}
          >
            {createMutation.isPending ? "Создаём…" : "Создать и выбрать"}
          </button>
        </div>
      ) : null}
    </div>
  );
}
