<?php

use App\Services\OpenStreetMapImportService;
use App\Services\WorldService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('world:import-osm {--preset=donostia} {--bbox=}', function (OpenStreetMapImportService $s) {
    app(WorldService::class)->seed();
    if ($this->option('preset') !== 'donostia' && ! $this->option('bbox')) {
        $this->error('Preset desconocido');

        return 1;
    }
    $bounds = $this->option('bbox') ? explode(',', $this->option('bbox')) : $s::DONOSTIA;
    $r = $s->import($bounds);
    $this->line($r->status.': '.$r->message);

    return $r->status === 'completed' ? 0 : 1;
});
Artisan::command('world:reset-demo {--force}', function (WorldService $s) {
    if (! $this->option('force') && ! $this->confirm('¿Eliminar el progreso demo?')) {
        return 1;
    }$s->reset();
    $this->info('Demo reiniciada.');
});
