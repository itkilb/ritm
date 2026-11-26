<?php

namespace App\Containers\AppSection\UserProfile\UI\API\Transformers;

use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class UserProfileTransformer extends ParentTransformer
{
    public function transform(UserProfile $profile): array
    {
        return [
            'weight_kg' => (float) $profile->weight_kg,
            'target_weight_kg' => null === $profile->target_weight_kg
                ? null
                : round((float) $profile->target_weight_kg, 3),
            'height_cm' => (float) $profile->height_cm,
            'birth_date' => $profile->birth_date->format('Y-m-d'),
            'fat_percentage' => is_null($profile->fat_percentage) ? null : (float) $profile->fat_percentage,
            'gender' => $profile->gender->value,
            'activity_level' => $profile->activity_level->value,
            'goal' => $profile->goal->value,
            'timezone' => $profile->timezone,
        ];
    }
}
