<?php

namespace App\Containers\AppSection\WeeklyStats\UI\API\Transformers;

final class WeeklyStatsTransformer
{
    /**
     * @param array<string, mixed> $summary
     *
     * @return array<string, mixed>
     */
    public function transform(array $summary): array
    {
        return [
            'start' => $summary['start'],
            'end' => $summary['end'],
            'timezone' => $summary['timezone'],
            'today' => $summary['today'],
            'days' => $summary['days'],
            'eaten_total' => $summary['eaten_total'],
            'has_entries' => $summary['has_entries'],
        ];
    }
}
