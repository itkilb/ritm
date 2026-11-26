<?php

namespace App\Containers\AppSection\Workout\UI\API\Requests;

use App\Containers\AppSection\Workout\Services\WorkoutMetricsCalculator;
use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use App\Ship\Rules\FiniteNumberRule;
use App\Ship\Rules\StrictIntegerRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWorkoutRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['strength', 'run', 'cardio'])],
            'name' => [
                'required',
                'string',
                'max:100',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || '' === trim($value)) {
                        $fail('Укажите название тренировки.');
                    }
                },
            ],
            'date' => ['required', 'string', new CalendarDateRule()],
            'duration_seconds' => [
                'required',
                new StrictIntegerRule('Длительность должна быть целым числом секунд.'),
                'integer',
                'min:60',
                'max:86400',
            ],
            'calories_mode' => ['nullable', 'string', Rule::in(['auto', 'manual'])],
            'calories' => [
                'nullable',
                new StrictIntegerRule('Калории должны быть целым числом.'),
                'integer',
                'min:0',
                'max:20000',
            ],
            'calorie_weight_kg' => [
                'nullable',
                'numeric',
                new FiniteNumberRule(),
                'min:35',
                'max:300',
            ],
            'strength_character' => [
                'nullable',
                'string',
                Rule::in(['ordinary', 'vigorous', 'circuit_moderate']),
            ],
            'activity_kind' => [
                'nullable',
                'string',
                Rule::in(['outdoor_bike', 'stationary_bike', 'elliptical', 'other']),
            ],
            'cardio_intensity' => [
                'nullable',
                'string',
                Rule::in(['easy', 'moderate', 'vigorous', 'general']),
            ],
            'power_watts' => [
                'nullable',
                new StrictIntegerRule('Мощность должна быть целым числом ватт.'),
                'integer',
                'min:0',
                'max:5000',
            ],
            'incline_ignored' => ['sometimes', 'boolean'],
            'calories_recalculate' => ['sometimes', 'boolean'],
            'feeling' => ['nullable', 'string', Rule::in(['easy', 'comfortable', 'hard'])],
            'note' => ['nullable', 'string', 'max:2000'],
            'input_mode' => ['nullable', 'string', Rule::in(['distance', 'speed'])],
            'distance_km' => [
                'nullable',
                'numeric',
                new FiniteNumberRule(),
                'min:0.001',
                'max:1000',
                'decimal:0,3',
            ],
            'speed_kmh' => [
                'nullable',
                'numeric',
                new FiniteNumberRule(),
                'min:0.1',
                'max:200',
                'decimal:0,2',
            ],
            'pulse' => [
                'nullable',
                new StrictIntegerRule('Пульс должен быть целым числом.'),
                'integer',
                'min:30',
                'max:250',
            ],
            'incline_percent' => [
                'nullable',
                'numeric',
                new FiniteNumberRule(),
                'min:0',
                'max:40',
                'decimal:0,1',
            ],
            'run_surface' => ['nullable', 'string', Rule::in(['outdoor_flat', 'treadmill'])],
            'exercises' => ['nullable', 'array', 'list', 'max:50'],
            'exercises.*' => ['array:exercise_id,name,mode,sets'],
            'exercises.*.exercise_id' => ['nullable', 'string', 'max:64'],
            'exercises.*.name' => [
                'required',
                'string',
                'max:100',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!is_string($value) || '' === trim($value)) {
                        $fail('Укажите название упражнения.');
                    }
                },
            ],
            'exercises.*.mode' => [
                'required',
                'string',
                Rule::in(['weighted', 'reps', 'time', 'bodyweight']),
            ],
            'exercises.*.sets' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'exercises.*.sets.*' => ['array:weight_kg,reps,seconds'],
            'exercises.*.sets.*.weight_kg' => [
                'nullable',
                'numeric',
                new FiniteNumberRule(),
                'min:0',
                'max:1000',
                'decimal:0,2',
            ],
            'exercises.*.sets.*.reps' => [
                'nullable',
                new StrictIntegerRule('Повторения должны быть целым числом.'),
                'integer',
                'min:1',
                'max:1000',
            ],
            'exercises.*.sets.*.seconds' => [
                'nullable',
                new StrictIntegerRule('Время подхода должно быть целым числом секунд.'),
                'integer',
                'min:1',
                'max:86400',
            ],

            // Snapshot fields are server-owned and never accepted from a client.
            'weight_kg' => ['prohibited'],
            'met_code' => ['prohibited'],
            'met_value' => ['prohibited'],
            'met_label' => ['prohibited'],
            'catalog_version' => ['prohibited'],
            'formula_version' => ['prohibited'],
            'active_kcal' => ['prohibited'],
            'gross_kcal' => ['prohibited'],
            'calories_status' => ['prohibited'],
            'calorie_method' => ['prohibited'],
            'weight_source' => ['prohibited'],
            'calorie_inputs' => ['prohibited'],
            'calorie_warnings' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCalorieMode($validator);
            $this->validateTypeSpecificFields($validator);
            $this->validateCalorieContext($validator);
            $this->validateExerciseModes($validator);
            $this->validateExerciseLinks($validator);
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Выберите тип тренировки.',
            'type.in' => 'Выберите тип тренировки.',
            'name.required' => 'Укажите название тренировки.',
            'name.max' => 'Название не должно превышать 100 символов.',
            'date.required' => 'Укажите дату тренировки.',
            'duration_seconds.required' => 'Укажите длительность тренировки.',
            'duration_seconds.integer' => 'Длительность должна быть целым числом секунд.',
            'duration_seconds.min' => 'Длительность должна быть не меньше одной минуты.',
            'duration_seconds.max' => 'Длительность не может превышать 24 часа.',
            'calories_mode.in' => 'Выберите режим расчёта калорий.',
            'calories.integer' => 'Калории должны быть целым числом.',
            'calories.min' => 'Калории не могут быть отрицательными.',
            'calories.max' => 'Калории не могут превышать 20 000.',
            'calorie_weight_kg.numeric' => 'Вес должен быть числом.',
            'calorie_weight_kg.min' => 'Вес должен быть не меньше 35 кг.',
            'calorie_weight_kg.max' => 'Вес не может превышать 300 кг.',
            'strength_character.in' => 'Выберите характер силовой тренировки.',
            'activity_kind.in' => 'Выберите вид кардиотренировки.',
            'cardio_intensity.in' => 'Выберите нагрузку из предложенных вариантов.',
            'power_watts.integer' => 'Мощность должна быть целым числом ватт.',
            'power_watts.min' => 'Мощность не может быть отрицательной.',
            'power_watts.max' => 'Слишком большая мощность.',
            'incline_ignored.boolean' => 'Подтверждение оценки без уклона имеет неверный формат.',
            'calories_recalculate.boolean' => 'Флаг пересчёта имеет неверный формат.',
            'feeling.in' => 'Выберите самочувствие из предложенных вариантов.',
            'note.max' => 'Заметка не должна превышать 2000 символов.',
            'input_mode.in' => 'Выберите способ ввода кардио.',
            'distance_km.numeric' => 'Дистанция должна быть числом.',
            'distance_km.min' => 'Дистанция должна быть больше нуля.',
            'distance_km.max' => 'Дистанция не может превышать 1000 км.',
            'distance_km.decimal' => 'Дистанция — не более трёх знаков после запятой.',
            'speed_kmh.numeric' => 'Скорость должна быть числом.',
            'speed_kmh.min' => 'Скорость должна быть больше нуля.',
            'speed_kmh.max' => 'Слишком большая скорость.',
            'speed_kmh.decimal' => 'Скорость — не более двух знаков после запятой.',
            'pulse.integer' => 'Пульс должен быть целым числом.',
            'pulse.min' => 'Пульс должен быть не меньше 30.',
            'pulse.max' => 'Пульс не может превышать 250.',
            'incline_percent.numeric' => 'Уклон должен быть числом.',
            'incline_percent.min' => 'Уклон не может быть отрицательным.',
            'incline_percent.max' => 'Уклон не может превышать 40%.',
            'incline_percent.decimal' => 'Уклон — не более одного знака после запятой.',
            'run_surface.in' => 'Выберите поверхность для бега.',
            'exercises.array' => 'Список упражнений должен быть массивом.',
            'exercises.max' => 'Слишком много упражнений.',
            'exercises.*.array' => 'Упражнение должно быть объектом.',
            'exercises.*.exercise_id.string' => 'Упражнение справочника указано неверно.',
            'exercises.*.exercise_id.max' => 'Упражнение справочника указано неверно.',
            'exercises.*.name.required' => 'Укажите название упражнения.',
            'exercises.*.name.max' => 'Название упражнения не должно превышать 100 символов.',
            'exercises.*.mode.required' => 'Выберите способ учёта упражнения.',
            'exercises.*.mode.in' => 'Выберите способ учёта упражнения.',
            'exercises.*.sets.required' => 'Добавьте хотя бы один подход.',
            'exercises.*.sets.min' => 'Добавьте хотя бы один подход.',
            'exercises.*.sets.max' => 'Слишком много подходов.',
            'exercises.*.sets.*.weight_kg.min' => 'Вес не может быть отрицательным.',
            'exercises.*.sets.*.weight_kg.max' => 'Слишком большой вес.',
            'exercises.*.sets.*.weight_kg.decimal' => 'Вес — не более двух знаков после запятой.',
            'exercises.*.sets.*.reps.min' => 'Повторения должны быть больше нуля.',
            'exercises.*.sets.*.reps.max' => 'Слишком много повторений.',
            'exercises.*.sets.*.seconds.min' => 'Время подхода должно быть больше нуля.',
            'exercises.*.sets.*.seconds.max' => 'Слишком долгий подход.',
            '*.prohibited' => 'Это поле рассчитывается сервером и не принимается от клиента.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }

        foreach ([
            'calories',
            'calories_mode',
            'calorie_weight_kg',
            'strength_character',
            'activity_kind',
            'cardio_intensity',
            'power_watts',
            'pulse',
            'incline_percent',
            'distance_km',
            'speed_kmh',
            'feeling',
            'note',
            'run_surface',
        ] as $field) {
            if ('' === $this->input($field)) {
                $this->merge([$field => null]);
            }
        }

        if ('' === $this->input('input_mode')) {
            $this->merge(['input_mode' => null]);
        }

        foreach (['incline_ignored', 'calories_recalculate'] as $field) {
            if ('' === $this->input($field)) {
                $this->merge([$field => false]);
            }
        }
    }

    private function validateCalorieMode(Validator $validator): void
    {
        if ('auto' === $this->input('calories_mode') && null !== $this->input('calories')) {
            $validator->errors()->add(
                'calories',
                'При автоматическом расчёте калории не передаются — их определяет сервер.',
            );
        }
    }

    private function validateTypeSpecificFields(Validator $validator): void
    {
        $type = $this->input('type');

        if (!is_string($type) || !in_array($type, ['strength', 'run', 'cardio'], true)) {
            return;
        }

        $exercises = $this->input('exercises');

        if ('strength' === $type) {
            foreach (['distance_km', 'speed_kmh', 'input_mode', 'pulse', 'incline_percent', 'run_surface', 'activity_kind', 'cardio_intensity', 'power_watts'] as $field) {
                if (null !== $this->input($field)) {
                    $validator->errors()->add($field, 'Это поле не относится к силовой тренировке.');
                }
            }

            if (true === $this->input('incline_ignored')) {
                $validator->errors()->add('incline_ignored', 'Это поле относится только к бегу с уклоном.');
            }

            if (!is_array($exercises) || [] === $exercises) {
                $validator->errors()->add('exercises', 'Добавьте хотя бы одно упражнение.');

                return;
            }

            return;
        }

        if (is_array($exercises) && [] !== $exercises) {
            $validator->errors()->add('exercises', 'Для кардиотренировки упражнения не указываются.');
        }

        if ('cardio' === $type && null !== $this->input('incline_percent')) {
            $validator->errors()->add('incline_percent', 'Уклон указывается только для бега.');
        }

        if ('run' === $type) {
            foreach (['activity_kind', 'cardio_intensity', 'power_watts'] as $field) {
                if (null !== $this->input($field)) {
                    $validator->errors()->add($field, 'Это поле не относится к бегу.');
                }
            }

            $incline = $this->input('incline_percent');

            if (true === $this->input('incline_ignored')
                && (null === $incline || (float) $incline <= 0)
            ) {
                $validator->errors()->add(
                    'incline_ignored',
                    'Подтверждение оценки без уклона возможно только при ненулевом уклоне.',
                );
            }

            $this->validateRunningDistance($validator);

            return;
        }

        if (null !== $this->input('run_surface')) {
            $validator->errors()->add('run_surface', 'Поверхность указывается только для бега.');
        }

        if (true === $this->input('incline_ignored')) {
            $validator->errors()->add('incline_ignored', 'Это поле относится только к бегу.');
        }

        $this->validateCardioDistance($validator);
    }

    private function validateRunningDistance(Validator $validator): void
    {
        $inputMode = $this->input('input_mode') ?? 'distance';

        if ('speed' === $inputMode) {
            if (null === $this->input('speed_kmh')) {
                $validator->errors()->add('speed_kmh', 'Укажите скорость для расчёта дистанции.');
            }

            if (null !== $this->input('distance_km')) {
                $validator->errors()->add('distance_km', 'Укажите либо дистанцию, либо скорость, не оба поля.');
            }

            $this->validateCalculatedDistance($validator);

            return;
        }

        if (null === $this->input('distance_km')) {
            $validator->errors()->add('distance_km', 'Укажите дистанцию.');
        }

        if (null !== $this->input('speed_kmh')) {
            $validator->errors()->add('speed_kmh', 'Укажите либо дистанцию, либо скорость, не оба поля.');
        }
    }

    private function validateCardioDistance(Validator $validator): void
    {
        $distance = $this->input('distance_km');
        $speed = $this->input('speed_kmh');
        $inputMode = $this->input('input_mode') ?? 'distance';

        if (null !== $distance && null !== $speed) {
            $validator->errors()->add('distance_km', 'Укажите либо дистанцию, либо скорость, не оба поля.');

            return;
        }

        if ('speed' === $inputMode && null !== $distance) {
            $validator->errors()->add('distance_km', 'При вводе скорости дистанция рассчитывается сервером.');

            return;
        }

        if ('distance' === $inputMode && null !== $speed) {
            $validator->errors()->add('speed_kmh', 'При вводе дистанции скорость рассчитывается сервером.');

            return;
        }

        if ('speed' === $inputMode && null !== $speed) {
            $this->validateCalculatedDistance($validator);
        }
    }

    private function validateCalculatedDistance(Validator $validator): void
    {
        if (
            $validator->errors()->has('speed_kmh')
            || $validator->errors()->has('duration_seconds')
        ) {
            return;
        }

        $speed = $this->input('speed_kmh');
        $duration = $this->input('duration_seconds');

        if (!is_numeric($speed) || !is_numeric($duration) || (int) $duration <= 0) {
            return;
        }

        $distance = app(WorkoutMetricsCalculator::class)->distanceFromSpeed(
            (float) $speed,
            (int) $duration,
        );

        if (null !== $distance && $distance > 1000) {
            $validator->errors()->add(
                'speed_kmh',
                'Расчётная дистанция не может превышать 1000 км.',
            );
        }
    }

    private function validateCalorieContext(Validator $validator): void
    {
        $type = $this->input('type');

        if ('cardio' !== $type) {
            return;
        }

        $kind = $this->input('activity_kind');
        $intensity = $this->input('cardio_intensity');
        $power = $this->input('power_watts');
        $distance = $this->input('distance_km');
        $speed = $this->input('speed_kmh');

        if (null === $kind) {
            if (null !== $intensity) {
                $validator->errors()->add('cardio_intensity', 'Сначала выберите вид кардиотренировки.');
            }

            if (null !== $power) {
                $validator->errors()->add('power_watts', 'Выберите велотренажёр для указания мощности.');
            }

            return;
        }

        if ('outdoor_bike' === $kind) {
            if (null !== $power) {
                $validator->errors()->add('power_watts', 'Мощность указывается только для велотренажёра.');
            }

            if (null !== $intensity && !in_array($intensity, ['easy', 'moderate', 'vigorous'], true)) {
                $validator->errors()->add('cardio_intensity', 'Для уличного велосипеда выберите лёгкую, умеренную или интенсивную нагрузку.');
            }

            if (null !== $intensity && (null !== $distance || null !== $speed)) {
                $validator->errors()->add('cardio_intensity', 'Укажите скорость или категорию нагрузки, не оба варианта.');
            }

            return;
        }

        if ('stationary_bike' === $kind) {
            if (null !== $intensity && 'general' !== $intensity) {
                $validator->errors()->add('cardio_intensity', 'Для велотренажёра без мощности доступна только «Общая тренировка».');
            }

            if (null !== $intensity && 'general' === $intensity && null !== $power) {
                $validator->errors()->add('power_watts', 'Укажите мощность или «Общую тренировку», не оба варианта.');
            }

            return;
        }

        if ('elliptical' === $kind) {
            if (null !== $power) {
                $validator->errors()->add('power_watts', 'Мощность указывается только для велотренажёра.');
            }

            if (null !== $intensity && !in_array($intensity, ['moderate', 'vigorous'], true)) {
                $validator->errors()->add('cardio_intensity', 'Для эллипса выберите умеренную или интенсивную нагрузку.');
            }

            return;
        }

        if (null !== $intensity) {
            $validator->errors()->add('cardio_intensity', 'Для этого вида кардио категория нагрузки не используется.');
        }

        if (null !== $power) {
            $validator->errors()->add('power_watts', 'Мощность указывается только для велотренажера.');
        }
    }

    private function validateExerciseLinks(Validator $validator): void
    {
        if ('strength' !== $this->input('type')) {
            return;
        }

        $exercises = $this->input('exercises');

        if (!is_array($exercises)) {
            return;
        }

        $resolver = app(\App\Containers\AppSection\Exercise\Tasks\ResolveExerciseForUserTask::class);
        $compatibility = app(\App\Containers\AppSection\Exercise\Services\ExerciseModeCompatibility::class);

        foreach ($exercises as $exerciseIndex => $exercise) {
            if (!is_array($exercise)) {
                continue;
            }

            $hashedId = $exercise['exercise_id'] ?? null;

            if (null === $hashedId || '' === $hashedId) {
                continue;
            }

            if (!is_string($hashedId)) {
                $validator->errors()->add(
                    "exercises.{$exerciseIndex}.exercise_id",
                    'Упражнение справочника указано неверно.',
                );

                continue;
            }

            $catalog = $resolver->run($this->user(), $hashedId);

            if (null === $catalog) {
                $validator->errors()->add(
                    "exercises.{$exerciseIndex}.exercise_id",
                    'Упражнение не найдено.',
                );

                continue;
            }

            $mode = $exercise['mode'] ?? null;

            if (is_string($mode) && !$compatibility::compatible($catalog->measurement_type, $mode)) {
                $validator->errors()->add(
                    "exercises.{$exerciseIndex}.mode",
                    'Способ учёта не совпадает с выбранным упражнением. Результаты подхода не изменены.',
                );
            }
        }
    }

    private function validateExerciseModes(Validator $validator): void
    {
        if ('strength' !== $this->input('type')) {
            return;
        }

        $exercises = $this->input('exercises');

        if (!is_array($exercises)) {
            return;
        }

        foreach ($exercises as $exerciseIndex => $exercise) {
            if (!is_array($exercise)) {
                continue;
            }

            $mode = $exercise['mode'] ?? null;
            $sets = $exercise['sets'] ?? null;

            if (!is_string($mode) || !in_array($mode, ['weighted', 'reps', 'time', 'bodyweight'], true) || !is_array($sets)) {
                continue;
            }

            foreach ($sets as $setIndex => $set) {
                if (!is_array($set)) {
                    continue;
                }

                $prefix = "exercises.{$exerciseIndex}.sets.{$setIndex}";
                $weight = $set['weight_kg'] ?? null;
                $reps = $set['reps'] ?? null;
                $seconds = $set['seconds'] ?? null;

                if ('weighted' === $mode) {
                    if (null === $weight) {
                        $validator->errors()->add("{$prefix}.weight_kg", 'Укажите вес для этого подхода.');
                    }
                    if (null === $reps) {
                        $validator->errors()->add("{$prefix}.reps", 'Укажите повторения для этого подхода.');
                    }
                    if (null !== $seconds) {
                        $validator->errors()->add("{$prefix}.seconds", 'Для этого способа учёта время не указывается.');
                    }

                    continue;
                }

                if ('bodyweight' === $mode) {
                    if (null === $reps) {
                        $validator->errors()->add("{$prefix}.reps", 'Укажите повторения для этого подхода.');
                    }
                    if (null !== $seconds) {
                        $validator->errors()->add("{$prefix}.seconds", 'Для этого способа учёта время не указывается.');
                    }

                    continue;
                }

                if ('reps' === $mode) {
                    if (null === $reps) {
                        $validator->errors()->add("{$prefix}.reps", 'Укажите повторения для этого подхода.');
                    }
                    if (null !== $weight) {
                        $validator->errors()->add("{$prefix}.weight_kg", 'Для этого способа учёта вес не указывается.');
                    }
                    if (null !== $seconds) {
                        $validator->errors()->add("{$prefix}.seconds", 'Для этого способа учёта время не указывается.');
                    }

                    continue;
                }

                if (null === $seconds) {
                    $validator->errors()->add("{$prefix}.seconds", 'Укажите время подхода в секундах.');
                }
                if (null !== $weight) {
                    $validator->errors()->add("{$prefix}.weight_kg", 'Для этого способа учёта вес не указывается.');
                }
                if (null !== $reps) {
                    $validator->errors()->add("{$prefix}.reps", 'Для этого способа учёта повторения не указываются.');
                }
            }
        }
    }
}
