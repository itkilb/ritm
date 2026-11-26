<?php

namespace App\Containers\AppSection\Exercise\UI\API\Transformers;

use App\Containers\AppSection\Exercise\Models\Exercise;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ExerciseTransformer extends ParentTransformer
{
    public function transform(Exercise $exercise): array
    {
        return [
            'id' => $exercise->getHashedKey(),
            'name' => $exercise->name,
            'muscle_group' => $exercise->muscle_group,
            'equipment' => $exercise->equipment,
            'measurement_type' => $exercise->measurement_type,
            'is_custom' => !$exercise->isGlobal(),
        ];
    }
}
