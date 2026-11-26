<?php

namespace App\Containers\AppSection\FoodEntry\Actions;

use App\Containers\AppSection\FoodEntry\Enums\MealType;
use App\Containers\AppSection\FoodEntry\Models\FoodEntry;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;

final class ChangeFoodEntryMealTypeAction extends ParentAction
{
    public function run(User $user, string $hashedId, string $mealType): FoodEntry
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

        $normalized = MealType::normalize($mealType);

        if (null === $normalized) {
            abort(422, 'Неизвестный приём пищи.');
        }

        $entry->forceFill(['meal_type' => $normalized->value])->save();

        return $entry->refresh();
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
