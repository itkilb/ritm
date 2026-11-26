<?php

namespace App\Containers\AppSection\WaterEntry\Actions;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class AddWaterAction extends ParentAction
{
    /**
     * @param array{date: string, amount_ml: int|string} $data
     */
    public function run(User $user, array $data): WaterEntry
    {
        $date = (string) $data['date'];
        $amountMl = (int) $data['amount_ml'];

        // The unique (user_id, date) key makes this upsert the serialization point.
        WaterEntry::query()->upsert(
            [[
                'user_id' => $user->getKey(),
                'date' => $date,
                'amount_ml' => $amountMl,
            ]],
            ['user_id', 'date'],
            [
                'amount_ml' => DB::raw('amount_ml + VALUES(amount_ml)'),
            ],
        );

        return WaterEntry::query()
            ->where('user_id', $user->getKey())
            ->where('date', $date)
            ->firstOrFail();
    }
}
