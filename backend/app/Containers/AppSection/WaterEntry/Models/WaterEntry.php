<?php

namespace App\Containers\AppSection\WaterEntry\Models;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WaterEntry extends ParentModel
{
    public const DAILY_TARGET_ML = 2000;

    public const GLASS_ML = 250;

    protected $fillable = [
        'user_id',
        'date',
        'amount_ml',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'amount_ml' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
