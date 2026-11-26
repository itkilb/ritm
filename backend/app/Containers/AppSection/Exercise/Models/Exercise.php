<?php

namespace App\Containers\AppSection\Exercise\Models;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Exercise extends ParentModel
{
    protected $fillable = [
        'user_id',
        'slug',
        'name',
        'muscle_group',
        'equipment',
        'measurement_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(static function (Builder $inner) use ($user): void {
            $inner->whereNull('exercises.user_id')
                ->orWhere('exercises.user_id', $user->getKey());
        });
    }

    public function isGlobal(): bool
    {
        return null === $this->user_id;
    }
}
