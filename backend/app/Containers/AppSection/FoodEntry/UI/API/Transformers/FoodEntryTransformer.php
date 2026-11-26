<?php

namespace App\Containers\AppSection\FoodEntry\UI\API\Transformers;

use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class FoodEntryTransformer extends ParentTransformer
{
    public function transform(FoodEntry $entry): array
    {
        return [
            'id' => $entry->getHashedKey(),
            'date' => $entry->date->format('Y-m-d'),
            'name' => $entry->name,
            'meal_type' => $entry->normalizedMealType()?->value,
            'calories' => (float) $entry->calories,
            'protein' => (float) $entry->protein,
            'fat' => (float) $entry->fat,
            'carbs' => (float) $entry->carbs,
        ];
    }
}
