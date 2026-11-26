<?php

namespace App\Containers\AppSection\Exercise\Data;

final class ExerciseCatalog
{
    public const TYPE_WEIGHTED = 'weighted';
    public const TYPE_REPS = 'reps';
    public const TYPE_TIME = 'time';
    public const TYPE_BODYWEIGHT = 'bodyweight';

    public const MEASUREMENT_TYPES = [
        self::TYPE_WEIGHTED,
        self::TYPE_REPS,
        self::TYPE_TIME,
        self::TYPE_BODYWEIGHT,
    ];

    public const MUSCLE_GROUPS = [
        'Грудь',
        'Спина',
        'Ноги',
        'Ягодицы',
        'Плечи',
        'Руки',
        'Пресс',
        'Икры',
        'Всё тело',
    ];

    public const EQUIPMENT = [
        'Штанга',
        'Гантели',
        'Тренажёр',
        'Кроссовер',
        'Собственный вес',
        'Гиря',
        'Резинка',
    ];

    /**
     * @return array<int, array{id: int, slug: string, name: string, muscle_group: string, equipment: string, measurement_type: string}>
     */
    public static function exercises(): array
    {
        return [
            ['id' => 1, 'slug' => 'bench-press-barbell', 'name' => 'Жим штанги лёжа', 'muscle_group' => 'Грудь', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 2, 'slug' => 'bench-press-dumbbell', 'name' => 'Жим гантелей лёжа', 'muscle_group' => 'Грудь', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 3, 'slug' => 'chest-press-machine', 'name' => 'Жим в тренажёре', 'muscle_group' => 'Грудь', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 4, 'slug' => 'push-ups', 'name' => 'Отжимания', 'muscle_group' => 'Грудь', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_BODYWEIGHT],
            ['id' => 5, 'slug' => 'cable-fly', 'name' => 'Сведения рук в кроссовере', 'muscle_group' => 'Грудь', 'equipment' => 'Кроссовер', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 6, 'slug' => 'dumbbell-fly', 'name' => 'Разведения гантелей лёжа', 'muscle_group' => 'Грудь', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 7, 'slug' => 'deadlift-barbell', 'name' => 'Становая тяга', 'muscle_group' => 'Спина', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 8, 'slug' => 'barbell-row', 'name' => 'Тяга штанги в наклоне', 'muscle_group' => 'Спина', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 9, 'slug' => 'one-arm-dumbbell-row', 'name' => 'Тяга гантели одной рукой', 'muscle_group' => 'Спина', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 10, 'slug' => 'pull-ups', 'name' => 'Подтягивания', 'muscle_group' => 'Спина', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_BODYWEIGHT],
            ['id' => 11, 'slug' => 'lat-pulldown', 'name' => 'Тяга верхнего блока', 'muscle_group' => 'Спина', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 12, 'slug' => 'seated-cable-row', 'name' => 'Тяга горизонтального блока', 'muscle_group' => 'Спина', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 13, 'slug' => 'hyperextension', 'name' => 'Гиперэкстензия', 'muscle_group' => 'Спина', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_BODYWEIGHT],
            ['id' => 14, 'slug' => 'back-squat', 'name' => 'Приседания со штангой', 'muscle_group' => 'Ноги', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 15, 'slug' => 'leg-press', 'name' => 'Жим ногами', 'muscle_group' => 'Ноги', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 16, 'slug' => 'dumbbell-lunges', 'name' => 'Выпады с гантелями', 'muscle_group' => 'Ноги', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 17, 'slug' => 'leg-extension', 'name' => 'Разгибания ног', 'muscle_group' => 'Ноги', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 18, 'slug' => 'leg-curl', 'name' => 'Сгибания ног', 'muscle_group' => 'Ноги', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 19, 'slug' => 'romanian-deadlift', 'name' => 'Румынская тяга', 'muscle_group' => 'Ноги', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 20, 'slug' => 'calf-raise', 'name' => 'Подъём на носки', 'muscle_group' => 'Икры', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 21, 'slug' => 'hip-thrust', 'name' => 'Ягодичный мост со штангой', 'muscle_group' => 'Ягодицы', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 22, 'slug' => 'overhead-press-barbell', 'name' => 'Жим штанги стоя', 'muscle_group' => 'Плечи', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 23, 'slug' => 'shoulder-press-dumbbell', 'name' => 'Жим гантелей сидя', 'muscle_group' => 'Плечи', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 24, 'slug' => 'lateral-raise', 'name' => 'Махи гантелями в стороны', 'muscle_group' => 'Плечи', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 25, 'slug' => 'reverse-fly-machine', 'name' => 'Разведения в тренажёре', 'muscle_group' => 'Плечи', 'equipment' => 'Тренажёр', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 26, 'slug' => 'upright-row', 'name' => 'Тяга штанги к подбородку', 'muscle_group' => 'Плечи', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 27, 'slug' => 'barbell-curl', 'name' => 'Подъём штанги на бицепс', 'muscle_group' => 'Руки', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 28, 'slug' => 'dumbbell-curl', 'name' => 'Подъём гантелей на бицепс', 'muscle_group' => 'Руки', 'equipment' => 'Гантели', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 29, 'slug' => 'cable-curl', 'name' => 'Сгибания на бицепс в кроссовере', 'muscle_group' => 'Руки', 'equipment' => 'Кроссовер', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 30, 'slug' => 'skull-crusher', 'name' => 'Французский жим', 'muscle_group' => 'Руки', 'equipment' => 'Штанга', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 31, 'slug' => 'triceps-pushdown', 'name' => 'Разгибания на трицепс в кроссовере', 'muscle_group' => 'Руки', 'equipment' => 'Кроссовер', 'measurement_type' => self::TYPE_WEIGHTED],
            ['id' => 32, 'slug' => 'dips', 'name' => 'Отжимания на брусьях', 'muscle_group' => 'Руки', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_BODYWEIGHT],
            ['id' => 33, 'slug' => 'plank', 'name' => 'Планка', 'muscle_group' => 'Пресс', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_TIME],
            ['id' => 34, 'slug' => 'crunches', 'name' => 'Скручивания', 'muscle_group' => 'Пресс', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_REPS],
            ['id' => 35, 'slug' => 'hanging-leg-raise', 'name' => 'Подъём ног в висе', 'muscle_group' => 'Пресс', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_REPS],
            ['id' => 36, 'slug' => 'side-plank', 'name' => 'Боковая планка', 'muscle_group' => 'Пресс', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_TIME],
            ['id' => 37, 'slug' => 'russian-twist', 'name' => 'Русские скручивания', 'muscle_group' => 'Пресс', 'equipment' => 'Собственный вес', 'measurement_type' => self::TYPE_REPS],
            ['id' => 38, 'slug' => 'kettlebell-swing', 'name' => 'Махи гирей', 'muscle_group' => 'Всё тело', 'equipment' => 'Гиря', 'measurement_type' => self::TYPE_WEIGHTED],
        ];
    }
}
