<?php

namespace Tests\Feature;

use App\Models\GameEvent;
use App\Models\Player;
use App\Models\World;
use App\Models\WorldLocation;
use App\Services\Classification;
use App\Services\LootGenerator;
use App\Services\OpenStreetMapImportService;
use App\Services\Simulation;
use App\Services\WorldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SurvivalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(WorldService::class)->seed();
    }

    private function world(): World
    {
        return World::where('dataset', 'demo')->firstOrFail();
    }

    private function place(string $category = 'supermarket'): WorldLocation
    {
        return WorldLocation::where('world_id', $this->world()->id)->where('category', $category)->firstOrFail();
    }

    private function act(string $action, array $data = [])
    {
        return $this->postJson('/api/v1/actions/'.$action, ['dataset' => 'demo', ...$data]);
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/osm.json')), true)['elements'];
    }

    public function test_central_prioritized_classification(): void
    {
        $c = app(Classification::class);
        $this->assertCount(18, $c::categories());
        foreach ($c::categories() as $r) {
            $this->assertSame($r['id'], $c->classify([$r['keys'][0] => $r['values'][0]])['category']);
        }
        $this->assertSame('pharmacy', $c->classify(['amenity' => 'pharmacy', 'landuse' => 'residential'])['category']);
        $this->assertSame('zone', $c->classify(['landuse' => 'industrial'])['kind']);
        $this->assertSame('unknown', $c->classify(['building' => 'yes'])['category']);
    }

    public function test_osm_geometry_nodes_ways_relations_and_missing_coordinates(): void
    {
        $s = app(OpenStreetMapImportService::class);
        $this->assertSame(3, $s->ingest($this->fixture()));
        $this->assertSame('pharmacy', WorldLocation::where('stable_id', 'osm:node:1001')->firstOrFail()->category);
        $this->assertSame('Polygon', WorldLocation::where('stable_id', 'osm:way:2001')->firstOrFail()->geometry['type']);
        $this->assertSame('MultiPolygon', WorldLocation::where('stable_id', 'osm:relation:3001')->firstOrFail()->geometry['type']);
        $this->assertTrue(DB::selectOne('SELECT bool_and(ST_IsValid(shape)) AS valid FROM world_locations WHERE shape IS NOT NULL')->valid);
        $this->assertNull($s->normalize(['type' => 'node', 'id' => 4, 'tags' => ['amenity' => 'pharmacy']]));
    }

    public function test_reimport_preserves_identity_stock_and_exploration(): void
    {
        $s = app(OpenStreetMapImportService::class);
        $s->ingest($this->fixture());
        $l = WorldLocation::where('stable_id', 'osm:node:1001')->firstOrFail();
        DB::table('location_resources')->where('location_id', $l->id)->update(['quantity' => 0]);
        $l->update(['visits' => 2]);
        $s->ingest($this->fixture());
        $this->assertSame(3, WorldLocation::where('source', 'osm')->count());
        $this->assertSame(2, $l->fresh()->visits);
        $this->assertEquals(0, DB::table('location_resources')->where('location_id', $l->id)->sum('quantity'));
    }

    public function test_generation_is_deterministic_and_category_specific(): void
    {
        $g = app(LootGenerator::class);
        $a = $g->generate('stable', 'pharmacy', 'seed');
        $b = $g->generate('stable', 'supermarket', 'seed');
        $this->assertSame($a, $g->generate('stable', 'pharmacy', 'seed'));
        $this->assertNotSame($a, $b);
        $this->assertArrayHasKey('bandage', $a);
        $this->assertArrayHasKey('water', $b);
        $this->assertArrayHasKey('cans', $b);
    }

    public function test_server_position_required(): void
    {
        $l = $this->place();
        $this->act('scavenge', ['location_id' => $l->id, 'lat' => $l->lat, 'lon' => $l->lon])->assertUnprocessable();
        $this->assertSame(0, GameEvent::count());
    }

    public function test_travel_loot_consume_and_persistence(): void
    {
        $l = $this->place();
        $before = DB::table('location_resources')->where('location_id', $l->id)->sum('quantity');
        $this->act('travel', ['location_id' => $l->id])->assertOk()->assertJsonPath('player.location_id', $l->id);
        $r = $this->act('scavenge', ['location_id' => $l->id])->assertOk();
        $found = array_sum(array_column($r->json('event.result.found'), 'quantity'));
        $this->assertGreaterThan(0, $found);
        $this->assertEquals($before - $found, DB::table('location_resources')->where('location_id', $l->id)->sum('quantity'));
        $weight = $r->json('weight');
        $this->assertGreaterThan(0, $weight);
        $this->getJson('/api/v1/world')->assertOk()->assertJsonPath('weight', $weight);
        app(WorldService::class)->seed();
        $this->assertEquals($before - $found, DB::table('location_resources')->where('location_id', $l->id)->sum('quantity'));
        Player::where('world_id', $this->world()->id)->update(['thirst' => 70]);
        $this->act('consume', ['resource_id' => 'water'])->assertOk()->assertJsonPath('player.thirst', 45);
        $this->assertSame(3, GameEvent::count());
    }

    public function test_capacity_and_nonnegative_finite_stock(): void
    {
        $l = $this->place();
        $this->act('travel', ['location_id' => $l->id])->assertOk();
        $p = Player::where('world_id', $this->world()->id)->first();
        $p->update(['capacity' => .05]);
        $before = DB::table('location_resources')->where('location_id', $l->id)->sum('quantity');
        $this->act('scavenge', ['location_id' => $l->id])->assertUnprocessable();
        $this->assertEquals($before, DB::table('location_resources')->where('location_id', $l->id)->sum('quantity'));
        $p->update(['capacity' => 200]);
        for ($i = 0; $i < 8; $i++) {
            $p->update(['energy' => 100]);
            $this->act('scavenge', ['location_id' => $l->id])->assertOk();
        }$this->assertFalse(DB::table('location_resources')->where('quantity', '<', 0)->exists());
        $this->assertEquals($before, DB::table('player_inventory')->where('player_id', $p->id)->sum('quantity'));
    }

    public function test_distance_and_authoritative_travel_cost(): void
    {
        $this->assertEqualsWithDelta(111.195, Simulation::distance(0, 0, 1, 0), .01);
        $this->assertEquals(0, Simulation::distance(43, -2, 43, -2));
        $l = $this->place();
        $minutes = max(1, (int) ceil(Simulation::distance(43.3183, -1.9812, $l->lat, $l->lon) * 1.3 / 4 * 60));
        $this->act('travel', ['location_id' => $l->id, 'minutes' => 0])->assertOk()->assertJsonPath('event.result.minutes', $minutes);
    }

    public function test_dataset_isolation_and_production_dev_protection(): void
    {
        $this->postJson('/api/v1/actions/travel', ['dataset' => 'osm', 'location_id' => $this->place()->id])->assertNotFound();
        app()->instance('env', 'production');
        $this->postJson('/api/v1/dev/reset')->assertNotFound();
        $this->postJson('/api/v1/dev/import')->assertNotFound();
        $this->getJson('/api/v1/dev/status')->assertNotFound();
    }

    public function test_overpass_failure_keeps_demo_usable(): void
    {
        Http::fake(['*' => Http::response('unavailable', 503)]);
        Cache::flush();
        $r = app(OpenStreetMapImportService::class)->import(OpenStreetMapImportService::DONOSTIA);
        $this->assertSame('failed', $r->status);
        $this->assertSame(18, WorldLocation::where('source', 'simulated')->count());
        $this->getJson('/api/v1/world')->assertOk();
    }

    public function test_safe_bounds_success_and_cache(): void
    {
        Http::fake(['*' => Http::response(['elements' => $this->fixture()], 200)]);
        Cache::flush();
        $s = app(OpenStreetMapImportService::class);
        $r = $s->import($s::DONOSTIA);
        $this->assertSame('completed', $r->status);
        $r->created_at = now()->subMinutes(2);
        $r->save();
        $s->import($s::DONOSTIA);
        Http::assertSentCount(1);
        $this->postJson('/api/v1/dev/import', ['bbox' => [0, 0, 80, 150]])->assertUnprocessable();
    }

    public function test_reset_preserves_osm(): void
    {
        app(OpenStreetMapImportService::class)->ingest($this->fixture());
        $l = $this->place();
        $this->act('travel', ['location_id' => $l->id]);
        $this->act('scavenge', ['location_id' => $l->id]);
        $this->postJson('/api/v1/dev/reset')->assertOk();
        $this->assertSame(3, WorldLocation::where('source', 'osm')->count());
        $this->assertSame(18, WorldLocation::where('source', 'simulated')->count());
        $this->assertSame(0, DB::table('player_inventory')->count());
    }

    public function test_geojson_filters_and_hidden_unexplored_stock(): void
    {
        $this->getJson('/api/v1/locations?category=pharmacy&bbox=-2.1,43.2,-1.8,43.4')->assertOk()->assertJsonCount(1, 'features')->assertJsonPath('features.0.geometry.type', 'Point');
        $this->getJson('/api/v1/locations/'.$this->place()->id)->assertOk()->assertJsonPath('stock', null);
        $this->getJson('/api/v1/locations?bbox=oops')->assertUnprocessable();
    }

    public function test_rest_and_reads_do_not_advance_offline_time(): void
    {
        $before = (int) $this->world()->minutes;
        Player::where('world_id', $this->world()->id)->update(['energy' => 20]);
        $this->getJson('/api/v1/world')->assertOk();
        $this->getJson('/api/v1/world')->assertOk();
        $this->assertSame($before, (int) $this->world()->minutes);
        $this->act('rest')->assertOk()->assertJsonPath('player.energy', 55);
        $this->assertSame($before + 60, (int) $this->world()->minutes);
    }

    public function test_split_relation_rings_and_holes_are_preserved(): void
    {
        $outer = [['lon' => -2.0, 'lat' => 43.3], ['lon' => -1.98, 'lat' => 43.3], ['lon' => -1.98, 'lat' => 43.32], ['lon' => -2.0, 'lat' => 43.32], ['lon' => -2.0, 'lat' => 43.3]];
        $inner = [['lon' => -1.995, 'lat' => 43.305], ['lon' => -1.99, 'lat' => 43.305], ['lon' => -1.99, 'lat' => 43.31], ['lon' => -1.995, 'lat' => 43.305]];
        $el = ['type' => 'relation', 'id' => 765, 'tags' => ['landuse' => 'forest'], 'members' => [['role' => 'outer', 'geometry' => array_slice($outer, 0, 3)], ['role' => 'outer', 'geometry' => array_slice($outer, 2)], ['role' => 'inner', 'geometry' => $inner]]];
        $n = app(OpenStreetMapImportService::class)->normalize($el);
        $this->assertSame('MultiPolygon', $n['geometry']['type']);
        $this->assertCount(2, $n['geometry']['coordinates'][0]);
        $this->assertGreaterThan(43.3, $n['lat']);
    }

    public function test_context_affects_initial_generation_only(): void
    {
        $g = app(LootGenerator::class);
        $low = $g->generate('id', 'pharmacy', 'seed', ['size' => .5, 'abundance' => .5]);
        $high = $g->generate('id', 'pharmacy', 'seed', ['size' => 2, 'abundance' => 2]);
        $this->assertGreaterThan(array_sum($low),array_sum($high));
        $l = $this->place();
        $stock = DB::table('location_resources')->where('location_id',$l->id)->sum('quantity');
        config(['survival.abundance' => 10]);
        app(WorldService::class)->seed();
        $this->assertEquals($stock,DB::table('location_resources')->where('location_id',$l->id)->sum('quantity'));
    }
}
