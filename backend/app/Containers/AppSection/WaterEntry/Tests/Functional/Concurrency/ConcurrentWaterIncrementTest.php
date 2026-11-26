<?php

namespace App\Containers\AppSection\WaterEntry\Tests\Functional\Concurrency;

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WaterEntry\Models\WaterEntry;
use App\Ship\Tests\ProductApiTestCase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ConcurrentWaterIncrementTest extends ProductApiTestCase
{
    protected array $connectionsToTransact = [];

    private User|null $createdUser = null;

    protected function tearDown(): void
    {
        try {
            if (null !== $this->createdUser) {
                DB::table('water_entries')->where('user_id', $this->createdUser->getKey())->delete();
                DB::table('users')->where('id', $this->createdUser->getKey())->delete();
            }
        } finally {
            $this->createdUser = null;
            parent::tearDown();
        }
    }

    public function testConcurrentFirstIncrementsCreateOneRowAndLoseNoUpdates(): void
    {
        $this->assertSame('ritm_test', DB::connection()->getDatabaseName(), 'Concurrency test must use the isolated test schema.');

        $user = User::factory()->createOne();
        $this->createdUser = $user;

        $worker = dirname(__DIR__, 2) . '/Support/water-increment-worker.php';
        $this->assertFileExists($worker);

        $workers = 8;
        $iterations = 40;
        $barrier = microtime(true) + 2.0;
        $processes = [];

        $env = getenv();
        $env['APP_ENV'] = 'testing';
        $env['DB_DATABASE'] = 'ritm_test';
        $env['QUEUE_CONNECTION'] = 'sync';
        $env['CACHE_STORE'] = 'array';
        $env['SESSION_DRIVER'] = 'array';
        $env['MAIL_MAILER'] = 'array';
        $env['LOG_CHANNEL'] = 'stderr';

        for ($i = 0; $i < $workers; ++$i) {
            $descriptors = [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $pipes = [];
            $process = proc_open(
                [PHP_BINARY, $worker, (string) $user->getKey(), '2025-01-15', (string) $iterations, sprintf('%.6f', $barrier)],
                $descriptors,
                $pipes,
                base_path(),
                $env,
            );

            $this->assertIsResource($process, 'Failed to start worker process.');
            $processes[] = [$process, $pipes];
        }

        foreach ($processes as [$process, $pipes]) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            $this->assertSame(0, $exitCode, 'Worker failed: ' . $stdout . $stderr);
            $this->assertStringContainsString('ok', (string) $stdout);
        }

        $rows = WaterEntry::query()->where('user_id', $user->getKey())->get();

        $this->assertCount(1, $rows, 'Concurrent first inserts must not create duplicate daily rows.');
        $this->assertSame(
            $workers * $iterations * WaterEntry::GLASS_ML,
            (int) $rows->first()->amount_ml,
        );
    }
}
