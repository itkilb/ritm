<?php

namespace App\Containers\AppSection\UserProfile\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;
use DateTimeZone;

final class ResolveUserTimezoneTask extends ParentTask
{
    public function run(User $user): string
    {
        // Profile timezone, then newest goal, then APP_TIMEZONE.
        $candidates = [
            $user->profile()->value('timezone'),
            $user->goals()->orderByDesc('id')->value('timezone'),
            config('app.timezone', 'UTC'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && '' !== $candidate && $this->isValidTimezone($candidate)) {
                return $candidate;
            }
        }

        return 'UTC';
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true);
    }
}
