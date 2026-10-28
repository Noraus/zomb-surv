<?php

use App\Models\GameEvent;
use App\Models\World;
use App\Models\WorldLocation;
use App\Services\Simulation;
use App\Services\WorldService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

// Real independent connections: intentionally outside RefreshDatabase transactions.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.connections.pgsql.database') !== 'survival_test') {
    throw new RuntimeException('Requires isolated survival_test database.');
}
if (($argv[1] ?? '') === '--worker') {
    $w = World::where('dataset', 'demo')->firstOrFail();
    app(Simulation::class)->act($w, 'scavenge', (int) $argv[2]);
    exit(0);
}
Artisan::call('migrate:fresh', ['--force' => true]);
app(WorldService::class)->seed();
$w = World::where('dataset', 'demo')->firstOrFail();
$l = WorldLocation::where('world_id', $w->id)->where('category', 'supermarket')->firstOrFail();
app(Simulation::class)->act($w, 'travel', $l->id);
DB::table('location_resources')->where('location_id', $l->id)->update(['quantity' => 0]);
DB::table('location_resources')->where('location_id', $l->id)->where('resource_id', 'water')->update(['quantity' => 3]);
$workers = [];
for ($i = 0; $i < 2; $i++) {
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, '--worker', (string) $l->id], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $workers[] = [$process, $pipes];
}
foreach ($workers as [$process,$pipes]) {
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }if (proc_close($process) !== 0) {
        throw new RuntimeException($out.$err);
    }
}
$stock = (int) DB::table('location_resources')->where('location_id', $l->id)->sum('quantity');
$inventory = (int) DB::table('player_inventory')->sum('quantity');
$events = GameEvent::where('type', 'scavenge')->count();
if ($stock !== 0 || $inventory !== 3 || $events !== 2) {
    throw new RuntimeException("Conservation failed: stock=$stock inventory=$inventory events=$events");
}
echo "PASS: 2 concurrent processes, 3 initial items, 3 transferred, 0 remaining, 2 audited events.\n";
