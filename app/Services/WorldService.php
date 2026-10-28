<?php

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Player;
use App\Models\ResourceDefinition;
use App\Models\World;
use App\Models\WorldLocation;
use Illuminate\Support\Facades\DB;

class WorldService
{
    public function seed(): void
    {
        DB::transaction(function () {
            foreach (LootGenerator::catalog() as $r) {
                ResourceDefinition::updateOrCreate(['id' => $r['id']], $r);
            }
            foreach (['demo', 'osm'] as $dataset) {
                $w = World::firstOrCreate(['dataset' => $dataset], ['seed' => config('survival.seed'), 'minutes' => 480]);
                Player::firstOrCreate(['world_id' => $w->id], ['name' => 'Alex', 'lat' => 43.3183, 'lon' => -1.9812, 'capacity' => config('survival.capacity')]);
                if ($dataset !== 'demo') {
                    continue;
                }
                foreach (Classification::categories() as $i => $r) {
                    $lat = 43.3183 + sin($i * 2.399) * (.002 + $i * .00055);
                    $lon = -1.9812 + cos($i * 2.399) * (.003 + $i * .0007);
                    $this->upsert($w, ['stable_id' => 'demo:'.$r['id'], 'name' => $r['name'].' · Sector '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'category' => $r['id'], 'kind' => 'poi', 'source' => 'simulated', 'rule' => 'demo:'.$r['id'], 'confidence' => 0, 'tags' => [], 'lat' => $lat, 'lon' => $lon, 'geometry' => null]);
                }
            }
        });
    }

    public function upsert(World $world, array $data): WorldLocation
    {
        return DB::transaction(function () use ($world, $data) {
            // All imports and player actions acquire the world lock first.
            World::whereKey($world->id)->lockForUpdate()->firstOrFail();
            $l = WorldLocation::firstOrNew(['world_id' => $world->id, 'stable_id' => $data['stable_id']]);
            $new = ! $l->exists;
            $l->fill($data);
            if ($new) {
                $size = 1;
                if ($l->geometry && in_array($l->geometry['type'], ['Polygon', 'MultiPolygon'])) {
                    $area = DB::selectOne('SELECT ST_Area(ST_SetSRID(ST_GeomFromGeoJSON(?),4326)::geography) AS area', [json_encode($l->geometry)])->area;
                    $size = min(2, max(.5, sqrt(max(1, $area) / 500)));
                }
                $zones = WorldLocation::where('world_id', $world->id)->where('kind', 'zone')->whereRaw('ST_Covers(shape,ST_SetSRID(ST_MakePoint(?,?),4326))', [$l->lon, $l->lat])->pluck('category')->all();
                $l->generation_context = ['size' => $size, 'zones' => $zones, 'abundance' => config('survival.abundance'), 'risk' => config('survival.risk')];
            }
            if ($new) {
                $base = config('survival.risk_by_category.'.$l->category, 30);
                $urban = in_array('residential', $zones) || in_array('industry', $zones);
                $l->risk = min(90, $base + ($urban ? 5 : 0) + hexdec(substr(hash('sha256', $world->seed.$data['stable_id']), 0, 3)) % 15);
            }
            $l->save();
            DB::update('UPDATE world_locations SET position=ST_SetSRID(ST_MakePoint(?,?),4326), shape=CASE WHEN ?::text IS NULL THEN NULL ELSE ST_SetSRID(ST_GeomFromGeoJSON(?),4326) END WHERE id=?', [$l->lon, $l->lat, $l->geometry ? json_encode($l->geometry) : null, $l->geometry ? json_encode($l->geometry) : null, $l->id]);
            if ($new && $l->kind === 'poi') {
                foreach (app(LootGenerator::class)->generate($l->stable_id, $l->category, $world->seed, $l->generation_context ?? []) as $id => $q) {
                    DB::table('location_resources')->insert(['location_id' => $l->id, 'resource_id' => $id, 'quantity' => $q]);
                }
            }

            return $l;
        });
    }

    public function reset(): void
    {
        DB::transaction(function () {
            $w = World::where('dataset', 'demo')->lockForUpdate()->firstOrFail();
            DB::table('events')->where('world_id', $w->id)->delete();
            $p = Player::where('world_id', $w->id)->firstOrFail();
            DB::table('player_inventory')->where('player_id', $p->id)->delete();
            $p->update(['location_id' => null, 'lat' => 43.3183, 'lon' => -1.9812, 'health' => 100, 'energy' => 100, 'hunger' => 15, 'thirst' => 10, 'survived' => 0]);
            WorldLocation::where('world_id', $w->id)->delete();
            $w->update(['minutes' => 480]);
            $this->seed();
        });
    }

    public function inventory(Player $p): array
    {
        return DB::table('player_inventory')->join('resource_definitions', 'resource_id', '=', 'resource_definitions.id')->where('player_id', $p->id)->where('quantity', '>', 0)->select('resource_definitions.*', 'quantity')->get()->map(function ($r) {
            $r->properties = json_decode($r->properties, true);

            return $r;
        })->all();
    }

    public function weight(Player $p): float
    {
        return (float) DB::table('player_inventory')->join('resource_definitions', 'resource_id', '=', 'resource_definitions.id')->where('player_id', $p->id)->selectRaw('COALESCE(SUM(quantity*weight),0) as weight')->value('weight');
    }

    public function state(World $w): array
    {
        $p = Player::where('world_id', $w->id)->firstOrFail();

        return ['world' => $w, 'player' => $p, 'inventory' => $this->inventory($p), 'weight' => $this->weight($p), 'visited' => WorldLocation::where('world_id', $w->id)->where('visits', '>', 0)->count()];
    }

    public function detail(WorldLocation $l): array
    {
        $probable = collect(LootGenerator::catalog())->whereIn('id', array_keys(app(LootGenerator::class)->generate($l->stable_id, $l->category, World::findOrFail($l->world_id)->seed, $l->generation_context ?? [])))->values();

        return ['location' => $l, 'probable' => $probable, 'stock' => $l->visits ? DB::table('location_resources')->join('resource_definitions', 'resource_id', '=', 'resource_definitions.id')->where('location_id', $l->id)->select('resource_id', 'name', 'quantity')->get() : null, 'history' => GameEvent::where('location_id', $l->id)->orderByDesc('id')->limit(8)->get(), 'scarcity' => $l->visits ? 'Explorado · existencias limitadas' : 'Desconocida'];
    }
}
