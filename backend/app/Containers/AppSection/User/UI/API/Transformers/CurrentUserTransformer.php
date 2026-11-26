<?php

namespace App\Containers\AppSection\User\UI\API\Transformers;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\UI\API\Transformers\UserProfileTransformer;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

/**
 * Minimal authenticated-user contract. It intentionally contains no password,
 * token, remember-token, role or other internal model fields.
 */
final class CurrentUserTransformer extends ParentTransformer
{
    public function transform(User $user): array
    {
        $profile = $user->profile;

        return [
            'id' => $user->getHashedKey(),
            'name' => $user->name,
            'email' => $user->email,
            'profile_completed' => $user->hasCompletedProfile(),
            // Only the onboarding contract fields; no internal ids or secrets.
            'profile' => $profile ? (new UserProfileTransformer())->transform($profile) : null,
        ];
    }
}
