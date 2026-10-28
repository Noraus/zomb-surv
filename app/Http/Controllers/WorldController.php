<?php

namespace App\Http\Controllers;

use App\Models\OsmImport;
use App\Models\World;
use App\Models\WorldLocation;
use App\Services\Classification;
use App\Services\OpenStreetMapImportService;
use App\Services\Simulation;
use App\Services\WorldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorldController extends Controller
{
    private function world(Request $r): World
    {
        $v = $r->validate(['dataset' => 'sometimes|in:demo,osm']);

        return World::where('dataset', $v['dataset'] ?? 'demo')->firstOrFail();
    }

    public function config(Request $r, WorldService $s)
    {
        $w = $this->world($r);

        return [...$s->state($w), 'categories' => Classification::categories(), 'map_style' => config('survival.map_style'), 'development' => app()->environment('local'), 'parameters' => ['travel_multiplier' => config('survival.travel_multiplier'), 'abundance' => config('survival.abundance'), 'risk' => config('survival.risk')]];
    }

    public function locations(Request $r)
    {
        $w = $this->world($r);
        $r->validate(['bbox' => 'sometimes|string|max:120', 'q' => 'sometimes|nullable|string|max:100', 'category' => 'sometimes|nullable|in:'.implode(',', array_column(Classification::categories(), 'id'))]);
        $query = WorldLocation::where('world_id', $w->id);
        if ($r->is('api/v1/zones')) {
            $query->where('kind', 'zone');
        }
        if ($r->filled('bbox')) {
            $b = explode(',', $r->bbox);
            if (count($b) !== 4 || count(array_filter($b, 'is_numeric')) !== 4) {
                abort(422, 'Bounding box inválido.');
            }[$west,$south,$east,$north] = array_map('floatval', $b);
            if ($west >= $east || $south >= $north || $west < -180 || $east > 180 || $south < -90 || $north > 90) {
                abort(422, 'Bounding box inválido.');
            }$query->whereRaw('ST_Intersects(position, ST_MakeEnvelope(?,?,?,?,4326))', [$west, $south, $east, $north]);
        }
        if ($r->filled('q')) {
            $query->where('name', 'ilike', '%'.$r->q.'%');
        }if ($r->filled('category')) {
            $query->where('category', $r->category);
        }
        $counts = (clone $query)->select('category', DB::raw('COUNT(*) AS total'))->groupBy('category')->pluck('total', 'category');
        if ($r->is('api/v1/statistics')) {
            return ['categories' => $counts, 'total' => array_sum($counts->all()), 'dataset' => $w->dataset];
        }
        $rows = $query->orderBy('id')->limit(1500)->get();
        $features = $rows->map(fn ($l) => ['type' => 'Feature', 'id' => $l->id, 'geometry' => $l->kind === 'zone' && $l->geometry ? $l->geometry : ['type' => 'Point', 'coordinates' => [$l->lon, $l->lat]], 'properties' => ['id' => $l->id, 'name' => $l->name, 'category' => $l->category, 'kind' => $l->kind, 'source' => $l->source, 'risk' => $l->risk, 'visits' => $l->visits, 'color' => collect(Classification::categories())->firstWhere('id', $l->category)['color'] ?? '#aaa']]);

        return ['type' => 'FeatureCollection', 'features' => $features, 'statistics' => $counts, 'total' => array_sum($counts->all()), 'limited' => array_sum($counts->all()) > 1500];
    }

    public function detail(int $id, WorldService $s)
    {
        return $s->detail(WorldLocation::findOrFail($id));
    }

    public function action(Request $r, string $action, Simulation $sim)
    {
        $r->validate(['location_id' => 'nullable|integer|min:1', 'resource_id' => 'nullable|string|max:50']);

        return $sim->act($this->world($r), $action, $r->integer('location_id') ?: null, $r->input('resource_id'));
    }

    public function dev(Request $r)
    {
        $w = $this->world($r);

        return ['seed' => $w->seed, 'minutes' => $w->minutes, 'counts' => WorldLocation::where('world_id', $w->id)->select('category', 'source', DB::raw('COUNT(*) AS total'))->groupBy('category', 'source')->get(), 'resources' => DB::table('location_resources')->join('world_locations', 'location_id', '=', 'world_locations.id')->where('world_id', $w->id)->sum('quantity'), 'imports' => OsmImport::latest('id')->limit(5)->get()];
    }

    public function import(Request $r, OpenStreetMapImportService $s)
    {
        $r->validate(['bbox' => 'nullable|array|size:4', 'bbox.*' => 'numeric']);
        $i = $s->import($r->input('bbox', OpenStreetMapImportService::DONOSTIA));

        return response()->json($i, $i->status === 'failed' ? 503 : 200);
    }

    public function reset(WorldService $s)
    {
        $s->reset();

        return ['message' => 'Demostración reiniciada.'];
    }
}
