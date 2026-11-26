<?php

namespace App\Containers\AppSection\WaterEntry\UI\API\Transformers;

use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class WaterEntryTransformer extends ParentTransformer
{
    public function transform(WaterEntry $entry): array
    {
        return [
            'date' => $entry->date->format('Y-m-d'),
            'amount_ml' => (int) $entry->amount_ml,
            'target_ml' => WaterEntry::DAILY_TARGET_ML,
        ];
    }
}
