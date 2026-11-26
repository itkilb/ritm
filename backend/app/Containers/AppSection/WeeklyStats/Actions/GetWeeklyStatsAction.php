<?php

namespace App\Containers\AppSection\WeeklyStats\Actions;

use App\Containers\AppSection\FoodEntry\Tasks\SumFoodCaloriesForDateTask;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\UserGoal\Tasks\ResolveUserGoalForDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserLocalDateTask;
use App\Containers\AppSection\UserProfile\Tasks\ResolveUserTimezoneTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Carbon\CarbonImmutable;

final class GetWeeklyStatsAction extends ParentAction
{
    private const DAYS_IN_WEEK = 7;

    public function __construct(
        private readonly ResolveUserTimezoneTask $resolveUserTimezoneTask,
        private readonly ResolveUserLocalDateTask $resolveUserLocalDateTask,
        private readonly ResolveUserGoalForDateTask $resolveUserGoalForDateTask,
        private readonly SumFoodCaloriesForDateTask $sumFoodCaloriesForDateTask,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(User $user, string|null $requestedStart): array
    {
        $timezone = $this->resolveUserTimezoneTask->run($user);
        $today = $this->resolveUserLocalDateTask->run($user);
        $start = $this->mondayOf($requestedStart ?? $today);
        $end = $this->addDays($start, self::DAYS_IN_WEEK - 1);

        $days = [];
        $eatenTotal = 0.0;
        $hasEntries = false;

        for ($offset = 0; $offset < self::DAYS_IN_WEEK; ++$offset) {
            $date = $this->addDays($start, $offset);
            $food = $this->sumFoodCaloriesForDateTask->run($user, $date);
            $goal = $this->resolveUserGoalForDateTask->run($user, $date);
            $target = null === $goal ? null : (int) $goal->calories;
            $eaten = $food['eaten'];

            if (null !== $eaten) {
                $eatenTotal += $eaten;
                $hasEntries = true;
            }

            $progress = null;
            $isOverTarget = false;
            $excess = null;

            if (null !== $target && $target > 0 && null !== $eaten) {
                $progress = min(1.0, $eaten / $target);
                $isOverTarget = $eaten > $target;
                $excess = round(max(0.0, $eaten - $target), 2);
            }

            $days[] = [
                'date' => $date,
                'eaten' => $eaten,
                'target' => $target,
                'has_entries' => $food['has_entries'],
                'progress' => null === $progress ? null : round($progress, 4),
                'is_over_target' => $isOverTarget,
                'excess' => $excess,
            ];
        }

        return [
            'start' => $start,
            'end' => $end,
            'timezone' => $timezone,
            'today' => $today,
            'days' => $days,
            'eaten_total' => round($eatenTotal, 2),
            'has_entries' => $hasEntries,
        ];
    }

    private function mondayOf(string $date): string
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');
        $isoWeekday = (int) $parsed->format('N');

        return $parsed->subDays($isoWeekday - 1)->format('Y-m-d');
    }

    private function addDays(string $date, int $days): string
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC')
            ->addDays($days)
            ->format('Y-m-d');
    }
}
