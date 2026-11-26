<?php

namespace App\Containers\AppSection\UserProfile\Data\Repositories;

use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of UserProfile
 *
 * @extends ParentRepository<TModel>
 */
final class UserProfileRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'id' => '=',
        'user_id' => '=',
    ];

    public function model(): string
    {
        return UserProfile::class;
    }
}
