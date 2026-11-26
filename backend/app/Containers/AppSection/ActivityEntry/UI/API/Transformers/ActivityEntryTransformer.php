<?php

namespace App\Containers\AppSection\ActivityEntry\UI\API\Transformers;

use App\Containers\AppSection\ActivityEntry\Models\ActivityEntry;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ActivityEntryTransformer extends ParentTransformer
{
    public function transform(ActivityEntry $entry): array
    {
        return [
            'id' => $entry->getHashedKey(),
            'date' => $entry->date->format('Y-m-d'),
            'name' => $entry->name,
            'calories' => (float) $entry->calories,
            'steps' => (int) $entry->steps,
            'minutes' => (float) $entry->minutes,
            'distance_km' => (float) $entry->distance_km,
        ];
    }
}
