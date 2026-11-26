<?php

namespace App\Containers\AppSection\User\Models;

use App\Containers\AppSection\Authorization\Enums\Role as RoleEnum;
use App\Containers\AppSection\User\Data\Collections\UserCollection;
use App\Containers\AppSection\User\Enums\Gender;
use App\Containers\AppSection\UserGoal\Models\UserGoal;
use App\Containers\AppSection\UserProfile\Models\UserProfile;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Models\UserModel as ParentUserModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class User extends ParentUserModel
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'gender',
        'birth',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'immutable_datetime',
        'password' => 'hashed',
        'gender' => Gender::class,
        'birth' => 'immutable_date',
        'profile_completed' => 'boolean',
    ];

    public function newCollection(array $models = []): UserCollection
    {
        return new UserCollection($models);
    }

    /**
     * Allows Passport to find the user by email (case-insensitive).
     */
    public function findForPassport(string $username): self|null
    {
        return self::orWhereRaw('lower(email) = lower(?)', [$username])->first();
    }

    /**
     * Integration point for task C. The flag is server-owned and is never
     * accepted from request input; profile+goal persistence will set it.
     */
    public function hasCompletedProfile(): bool
    {
        // A freshly created model may not have hydrated DB defaults yet. Read
        // the raw attributes so the flag is still authoritative and false.
        return (bool) ($this->getAttributes()['profile_completed'] ?? false);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Goal history. Old snapshots stay untouched; the current goal is resolved
     * by the latest effective_date/id task, never by mutating past rows.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(UserGoal::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function weightEntries(): HasMany
    {
        return $this->hasMany(WeightEntry::class);
    }

    public function isSuperAdmin(): bool
    {
        foreach (array_keys(config('auth.guards')) as $guard) {
            if (!$this->hasRole(RoleEnum::SUPER_ADMIN, $guard)) {
                return false;
            }
        }

        return true;
    }

    protected function email(): Attribute
    {
        return new Attribute(
            get: static fn (string|null $value): string|null => is_null($value) ? null : strtolower($value),
        );
    }
}
