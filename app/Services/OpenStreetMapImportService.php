<?php

namespace App\Services;

use App\Models\OsmImport;
use App\Models\World;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OpenStreetMapImportService
{
    public const DONOSTIA = [43.305, -2.005, 43.335, -1.965];

    public function bounds(array $bbox): array
    {
        if (count($bbox) !== 4 || count(array_filter($bbox, 'is_numeric')) !== 4) {
            throw ValidationException::withMessages(['bbox' => 'Usa sur,oeste,norte,este.']);
        }
        [$s,$w,$n,$e] = array_map('floatval', $bbox);
        if ($s < -85 || $n > 85 || $w < -180 || $e > 180 || $s >= $n || $w >= $e || ($n - $s) > .08 || ($e - $w) > .1 || ($n - $s) * 111.2 * ($e - $w) * 111.2 * cos(deg2rad(($s + $n) / 2)) > 45) {
            throw ValidationException::withMessages(['bbox' => 'Área inválida: máximo 45 km² y 0,08° × 0,1°.']);
        }

        return [$s, $w, $n, $e];
    }

    public function normalize(array $el): ?array
    {
        $type = $el['type'] ?? '';
        if (! in_array($type, ['node', 'way', 'relation']) || ! isset($el['id'])) {
            return null;
        }
        $tags = $el['tags'] ?? [];
        $c = app(Classification::class)->classify($tags);
        if ($c['category'] === 'unknown') {
            return null;
        }
        $lat = $el['lat'] ?? $el['center']['lat'] ?? null;
        $lon = $el['lon'] ?? $el['center']['lon'] ?? null;
        $geometry = null;
        $coords = static fn ($g) => array_map(fn ($v) => [(float) $v['lon'], (float) $v['lat']], $g);
        if (! empty($el['geometry'])) {
            $points = $coords($el['geometry']);
            if (count($points) >= 4 && $points[0] === end($points)) {
                $geometry = ['type' => 'Polygon', 'coordinates' => [$points]];
            } elseif (count($points) >= 2) {
                $geometry = ['type' => 'LineString', 'coordinates' => $points];
            }
        }
        if ($type === 'relation') {
            $outerParts = [];
            $innerParts = [];
            foreach ($el['members'] ?? [] as $m) {
                if (! empty($m['geometry'])) {
                    if (($m['role'] ?? '') === 'inner') {
                        $innerParts[] = $coords($m['geometry']);
                    } else {
                        $outerParts[] = $coords($m['geometry']);
                    }
                }
            }
            $outers = $this->rings($outerParts);
            $inners = $this->rings($innerParts);
            if ($outers && (! $innerParts || $inners)) {
                $polygons = array_map(fn ($ring) => [$ring], $outers);
                $complete = true;
                foreach ($inners as $inner) {
                    $assigned = false;
                    foreach ($outers as $i => $outer) {
                        $covers = DB::selectOne('SELECT ST_Covers(ST_GeomFromGeoJSON(?),ST_GeomFromGeoJSON(?)) AS covers', [json_encode(['type' => 'Polygon', 'coordinates' => [$outer]]), json_encode(['type' => 'Polygon', 'coordinates' => [$inner]])])->covers;
                        if ($covers) {
                            $polygons[$i][] = $inner;
                            $assigned = true;
                            break;
                        }
                    }
                    if (! $assigned) {
                        $complete = false;
                    }
                }
                if ($complete) {
                    $geometry = ['type' => 'MultiPolygon', 'coordinates' => $polygons];
                }
            }
        }
        if (($lat === null || $lon === null) && $geometry) {
            // Derive a representative point from supplied geometry, never random coordinates.
            $g = DB::selectOne('SELECT ST_Y(ST_PointOnSurface(ST_SetSRID(ST_GeomFromGeoJSON(?),4326))) AS lat, ST_X(ST_PointOnSurface(ST_SetSRID(ST_GeomFromGeoJSON(?),4326))) AS lon', [json_encode($geometry), json_encode($geometry)]);
            $lat = $g->lat;
            $lon = $g->lon;
        }
        if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) {
            return null;
        }
        if ($geometry) {
            $valid = DB::selectOne('SELECT ST_IsValid(ST_GeomFromGeoJSON(?)) AS valid', [json_encode($geometry)]);
            if (! $valid->valid) {
                $geometry = null;
            }
        }

        return ['stable_id' => "osm:$type:{$el['id']}", 'osm_type' => $type, 'osm_id' => $el['id'], 'name' => $tags['name'] ?? (collect(Classification::categories())->firstWhere('id', $c['category'])['name'].' sin nombre'), ...$c, 'source' => 'osm', 'tags' => $tags, 'lat' => (float) $lat, 'lon' => (float) $lon, 'geometry' => $geometry];
    }

    private function rings(array $parts): array
    {
        $rings = [];
        while ($parts) {
            $chain = array_shift($parts);
            if (count($chain) < 2) {
                continue;
            }
            while ($chain[0] !== end($chain)) {
                $joined = false;
                foreach ($parts as $i => $part) {
                    if (end($chain) === $part[0]) {
                        $chain = array_merge($chain, array_slice($part, 1));
                    } elseif (end($chain) === end($part)) {
                        $chain = array_merge($chain, array_slice(array_reverse($part), 1));
                    } elseif ($chain[0] === end($part)) {
                        $chain = array_merge($part, array_slice($chain, 1));
                    } elseif ($chain[0] === $part[0]) {
                        $chain = array_merge(array_reverse($part), array_slice($chain, 1));
                    } else {
                        continue;
                    }
                    unset($parts[$i]);
                    $joined = true;
                    break;
                }
                if (! $joined) {
                    break;
                }
            }
            if (count($chain) >= 4 && $chain[0] === end($chain)) {
                $rings[] = $chain;
            } else {
                return [];
            }
        }

        return $rings;
    }

    public function import(array $bbox): OsmImport
    {
        $bbox = $this->bounds($bbox);

        return Cache::lock('osm-import', 90)->block(1, function () use ($bbox) {
            $last = OsmImport::latest('id')->first();
            if ($last && $last->created_at->gt(now()->subMinute())) {
                throw ValidationException::withMessages(['import' => 'Espera un minuto entre importaciones.']);
            }
            $record = OsmImport::create(['status' => 'running', 'bbox' => $bbox]);
            try {
                $key = 'osm:'.hash('sha256', json_encode($bbox));
                $data = Cache::remember($key, 3600, function () use ($bbox) {
                    $b = implode(',', $bbox);
                    $query = '[out:json][timeout:25][maxsize:16777216];(nwr[amenity~"^(hospital|clinic|doctors|pharmacy|police|fuel|restaurant|cafe)$"]('.$b.');nwr[shop~"^(supermarket|convenience|greengrocer|mall|hardware|doityourself|car_repair)$"]('.$b.');nwr[building~"^(warehouse|industrial)$"]('.$b.');nwr[landuse~"^(industrial|forest|farmland|residential)$"]('.$b.');nwr[natural~"^(wood|water)$"]('.$b.');nwr[leisure=park]('.$b.');nwr[waterway]('.$b.'););out center geom;';
                    $response = Http::withHeaders(['User-Agent' => 'DonostiaSurvivalPrototype/0.1 (local development; explicit small-area import)', 'Accept' => 'application/json'])->connectTimeout(10)->timeout(40)->retry(2, 1500, throw: false)->asForm()->post(config('survival.overpass'), ['data' => $query]);
                    $response->throw();
                    $data = $response->json();
                    if (! is_array($data['elements'] ?? null) || isset($data['remark'])) {
                        throw new \RuntimeException('Respuesta Overpass incompleta.');
                    }

return $data;
                });
                $count = $this->ingest($data['elements']);
                $record->update(['status' => 'completed', 'count' => $count, 'message' => 'Datos cartográficos actualizados; botín conservado.']);
            } catch (\Throwable $e) {
                report($e);
                $record->update(['status' => 'failed', 'message' => 'No se pudo completar Overpass. El mundo demo sigue disponible; consulta el registro del servidor.']);
            }

            return $record->fresh();
        });
    }

    public function ingest(array $elements): int
    {
        $world = World::where('dataset', 'osm')->firstOrFail();
        $count = 0;
        DB::transaction(function () use ($world, $elements, &$count) {
            $normalized = [];
            foreach ($elements as $el) {
                if ($data = $this->normalize($el)) {
                    $normalized[] = $data;
                }
            }
            usort($normalized, fn ($a, $b) => ($a['kind'] === 'zone' ? 0 : 1) <=> ($b['kind'] === 'zone' ? 0 : 1));
            foreach ($normalized as $data) {
                app(WorldService::class)->upsert($world,$data);
                $count++;
            }
        });

        return $count;
    }
}
