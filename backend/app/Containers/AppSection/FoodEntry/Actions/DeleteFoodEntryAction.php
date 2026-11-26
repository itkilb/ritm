<?php

namespace App\Containers\AppSection\FoodEntry\Actions;

use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;

final class DeleteFoodEntryAction extends ParentAction
{
    public function run(User $user, string $hashedId): void
    {
        $id = $this->decodeId($hashedId);

        $entry = null === $id
            ? null
            : FoodEntry::query()
                ->where('user_id', $user->getKey())
                ->whereKey($id)
                ->first();

        if (null === $entry) {
            abort(404, 'Запись не найдена.');
        }

        $entry->delete();
    }

    private function decodeId(string $hashedId): int|null
    {
        try {
            $decoded = hashids()->decode($hashedId);
        } catch (\Throwable) {
            return null;
        }

        return is_int($decoded) ? $decoded : null;
    }
}
