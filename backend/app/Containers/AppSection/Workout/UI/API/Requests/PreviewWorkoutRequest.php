<?php

namespace App\Containers\AppSection\Workout\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;
use App\Ship\Rules\CalendarDateRule;
use App\Ship\Rules\FiniteNumberRule;
use App\Ship\Rules\StrictIntegerRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class PreviewWorkoutRequest extends ParentRequest
{
    protected array $decode = [];

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['strength', 'run', 'cardio'])],
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
            // No decimal cap: an exact saved weight such as 80.265 must round-trip.
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
            'workout_id' => ['nullable', 'string', 'max:100'],

            'weight_kg' => ['prohibited'],
            'weight_kg_exact' => ['prohibited'],
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
            if ('auto' === $this->input('calories_mode') && null !== $this->input('calories')) {
                $validator->errors()->add(
                    'calories',
                    'При автоматическом расчёте калории не передаются — их определяет сервер.',
                );
            }

            if (null !== $this->input('distance_km') && null !== $this->input('speed_kmh')) {
                $validator->errors()->add('distance_km', 'Укажите либо дистанцию, либо скорость, не оба поля.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Выберите тип тренировки.',
            'type.in' => 'Выберите тип тренировки.',
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
            'workout_id.string' => 'Некорректный идентификатор тренировки.',
            '*.prohibited' => 'Это поле рассчитывается сервером и не принимается от клиента.',
        ];
    }

    protected function prepareForValidation(): void
    {
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
            'run_surface',
            'input_mode',
        ] as $field) {
            if ('' === $this->input($field)) {
                $this->merge([$field => null]);
            }
        }

        foreach (['incline_ignored', 'calories_recalculate'] as $field) {
            if ('' === $this->input($field)) {
                $this->merge([$field => false]);
            }
        }
    }
}
