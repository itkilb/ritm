<?php

namespace App\Containers\AppSection\WeightEntry\Models;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WeightEntry extends ParentModel
{
    protected $fillable = [
        'user_id',
        'date',
        'weight_kg',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'weight_kg' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
