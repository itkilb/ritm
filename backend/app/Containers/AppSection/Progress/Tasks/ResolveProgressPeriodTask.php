<?php

namespace App\Containers\AppSection\Progress\Tasks;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserTimezoneTask;
use App\Containers\AppSection\WeightEntry\Models\WeightEntry;
use App\Containers\AppSection\Workout\Models\Workout;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Carbon\CarbonImmutable;

final class ResolveProgressPeriodTask extends ParentTask
{
    public const PERIODS = ['month', '3months', 'year', 'all'];

    public function __construct(
        private readonly ResolveUserTimezoneTask $resolveUserTimezoneTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
    ) {
    }

    /**
     * @return array{period: string, start_date: string, end_date: string, timezone: string, today: string}
     */
    public function run(User $user, string|null $period, string|null $date): array
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'month';
        $timezone = $this->resolveUserTimezoneTask->run($user);
        $today = $this->resolveUserLocalDateTask->run($user);
        $end = $date ?? $today;

        if ($end > $today) {
            $end = $today;
        }

        $anchor = CarbonImmutable::createFromFormat('!Y-m-d', $end, 'UTC');
        $start = match ($period) {
            '3months' => $anchor->startOfMonth()->subMonthsNoOverflow(2)->format('Y-m-d'),
            'year' => $anchor->startOfYear()->format('Y-m-d'),
            'all' => $this->earliestDate($user) ?? $end,
            default => $anchor->startOfMonth()->format('Y-m-d'),
        };

        if ($start > $end) {
            $start = $end;
        }

        return [
            'period' => $period,
            'start_date' => $start,
            'end_date' => $end,
            'timezone' => $timezone,
            'today' => $today,
        ];
    }

    private function earliestDate(User $user): string|null
    {
        $weightDate = WeightEntry::query()
            ->where('user_id', $user->getKey())
            ->min('date');
        $workoutDate = Workout::query()
            ->where('user_id', $user->getKey())
            ->min('date');

        $dates = array_values(array_filter([$weightDate, $workoutDate]));

        if ([] === $dates) {
            return null;
        }

        sort($dates);

        return (string) $dates[0];
    }
}
