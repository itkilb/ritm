<?php

namespace App\Containers\AppSection\ActivityEntry\Models;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ActivityEntry extends ParentModel
{
    protected $fillable = [
        'user_id',
        'date',
        'name',
        'calories',
        'steps',
        'minutes',
        'distance_km',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'calories' => 'float',
        'steps' => 'integer',
        'minutes' => 'float',
        'distance_km' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
