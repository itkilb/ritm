<?php

namespace App\Containers\AppSection\UserGoal\Data\Repositories;

use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Ship\Parents\Repositories\Repository as ParentRepository;

/**
 * @template TModel of UserGoal
 *
 * @extends ParentRepository<TModel>
 */
final class UserGoalRepository extends ParentRepository
{
    protected $fieldSearchable = [
        'id' => '=',
        'user_id' => '=',
        'effective_date' => '=',
    ];

    public function model(): string
    {
        return UserGoal::class;
    }
}
