<?php

declare(strict_types=1);

use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\WaterEntry\Actions\AddWaterAction;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 6);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 0);
$date = (string) ($argv[2] ?? '');
$iterations = (int) ($argv[3] ?? 0);
$barrier = (float) ($argv[4] ?? 0);

if ($userId <= 0 || '' === $date || $iterations <= 0) {
    fwrite(STDERR, "usage: worker.php <user-id> <date> <iterations> <barrier>\n");
    exit(2);
}

$user = User::query()->findOrFail($userId);
$action = $app->make(AddWaterAction::class);

$delay = $barrier - microtime(true);

if ($delay > 0) {
    usleep((int) round($delay * 1_000_000));
}

for ($i = 0; $i < $iterations; ++$i) {
    $action->run($user, ['date' => $date, 'amount_ml' => 250]);
}

echo "ok\n";
