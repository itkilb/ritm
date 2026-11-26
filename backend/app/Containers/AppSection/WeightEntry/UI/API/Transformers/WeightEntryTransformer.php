<?php

namespace App\Containers\AppSection\WeightEntry\UI\API\Transformers;

use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class WeightEntryTransformer extends ParentTransformer
{
    public function transform(WeightEntry $entry): array
    {
        return [
            'id' => $entry->getHashedKey(),
            'date' => $entry->date->format('Y-m-d'),
            'weight_kg' => round((float) $entry->weight_kg, 3),
        ];
    }
}
