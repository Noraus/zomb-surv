<?php

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Player;
use App\Models\ResourceDefinition;
use App\Models\World;
use App\Models\WorldLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Simulation
{
    public static function distance(float $lat, float $lon, float $toLat, float $toLon): float
    {
        $a = sin(deg2rad($toLat - $lat) / 2) ** 2 + cos(deg2rad($lat)) * cos(deg2rad($toLat)) * sin(deg2rad($toLon - $lon) / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    public function fail(string $m): never
    {
        throw ValidationException::withMessages(['action' => $m]);
    }

    private function tick(World $w, Player $p, int $minutes, int $energy): void
    {
        $w->minutes += $minutes;
        $p->survived += $minutes;
        $p->energy = max(0, min(100, $p->energy - $energy));
        $p->hunger = min(100, $p->hunger + max(1, (int) ceil($minutes / 15)));
        $p->thirst = min(100, $p->thirst + max(1, (int) ceil($minutes / 10)));
        if ($p->hunger >= 100 || $p->thirst >= 100) {
            $p->health = max(1, $p->health - 3);
        }
    }

    public function act(World $world, string $action, ?int $locationId = null, ?string $resourceId = null): array
    {
        return DB::transaction(function () use ($world, $action, $locationId, $resourceId) {
            $w = World::whereKey($world->id)->lockForUpdate()->firstOrFail();
            $p = Player::where('world_id', $w->id)->lockForUpdate()->firstOrFail();
            $l = $locationId ? WorldLocation::where('world_id', $w->id)->whereKey($locationId)->lockForUpdate()->firstOrFail() : null;
            $initialHealth = $p->health;
            $initialEnergy = $p->energy;
            $initialMinutes = $w->minutes;
            $result = ['title' => 'Acción completada', 'minutes' => 0, 'energy' => 0, 'health' => 0, 'found' => [], 'risk' => 'Sin incidentes'];
            if ($action === 'travel') {
                if (! $l || $l->kind !== 'poi') {
                    $this->fail('Selecciona una localización visitable.');
                }
                $km = self::distance($p->lat, $p->lon, $l->lat, $l->lon);
                $minutes = max(1, (int) ceil($km * config('survival.travel_multiplier') / 4 * 60));
                $cost = max(1, (int) ceil($minutes * .45));
                if ($p->energy < $cost) {
                    $this->fail('Energía insuficiente. Descansa antes de viajar.');
                }
                $p->lat = $l->lat;
                $p->lon = $l->lon;
                $p->location_id = $l->id;
                $result = array_replace($result, ['title' => 'Destino alcanzado', 'minutes' => $minutes, 'energy' => $cost, 'distance_km' => round($km, 3), 'estimate' => 'Línea recta × '.config('survival.travel_multiplier').' a 4 km/h; no es una ruta real.']);
                $this->tick($w, $p, $minutes, $cost);
            } elseif ($action === 'scavenge') {
                if (! $l || $l->kind !== 'poi' || $p->location_id !== $l->id || self::distance($p->lat, $p->lon, $l->lat, $l->lon) > .03) {
                    $this->fail('Debes viajar hasta esta localización antes de registrarla.');
                }
                $rules = config('survival.scavenge');
                if ($p->energy < $rules['energy']) {
                    $this->fail('Necesitas al menos 8 puntos de energía.');
                }
                $free = $p->capacity - app(WorldService::class)->weight($p);
                if ($free < .1) {
                    $this->fail('La mochila está llena. Consume alimentos o agua.');
                }
                $stocks = DB::table('location_resources')->where('location_id', $l->id)->where('quantity', '>', 0)->orderBy('id')->lockForUpdate()->get();
                $found = [];
                foreach ($stocks as $s) {
                    $r = ResourceDefinition::findOrFail($s->resource_id);
                    $quantity = min($s->quantity, 2, (int) floor(($free + .00001) / $r->weight));
                    if ($quantity <= 0) {
                        continue;
                    }
                    DB::table('location_resources')->where('id', $s->id)->decrement('quantity', $quantity);
                    DB::statement('INSERT INTO player_inventory(player_id,resource_id,quantity) VALUES(?,?,?) ON CONFLICT(player_id,resource_id) DO UPDATE SET quantity=player_inventory.quantity+EXCLUDED.quantity', [$p->id, $r->id, $quantity]);
                    $free -= $quantity * $r->weight;
                    $found[] = ['resource_id' => $r->id, 'name' => $r->name, 'quantity' => $quantity];
                    if (count($found) >= 3) {
                        break;
                    }
                }
                if ($stocks->count() && ! $found) {
                    $this->fail('No queda capacidad suficiente para estos objetos.');
                }
                $roll = hexdec(substr(hash('sha256', $w->seed.':'.$l->stable_id.':'.$l->visits), 0, 4)) % 100;
                $risk = min($rules['maximum_risk'], (int) (($l->risk + min(20, $l->visits * $rules['visit_risk'])) * config('survival.risk')));
                $damage = $roll < $risk ? $rules['injury_damage'] : 0;
                $cost = $rules['energy'] + ($roll < $risk ? $rules['extra_energy'] : 0);
                $p->health = max(1, $p->health - $damage);
                $l->visits++;
                $l->last_visit = $w->minutes + $rules['minutes'];
                $l->save();
                $this->tick($w, $p, $rules['minutes'], $cost);
                $result = array_replace($result, ['title' => 'Expedición completada', 'minutes' => $rules['minutes'], 'energy' => $cost, 'health' => -$damage, 'found' => $found, 'risk' => $damage ? 'Infectados cerca: herida leve y esfuerzo adicional.' : 'Sin incidentes', 'remaining' => DB::table('location_resources')->where('location_id', $l->id)->sum('quantity')]);
            } elseif ($action === 'rest') {
                $this->tick($w, $p, 60, -35);
                $result = array_replace($result, ['title' => 'Descanso completado', 'minutes' => 60, 'energy' => -35]);
            } elseif ($action === 'consume') {
                $r = ResourceDefinition::findOrFail($resourceId);
                $s = DB::table('player_inventory')->where('player_id', $p->id)->where('resource_id', $r->id)->lockForUpdate()->first();
                if (! $s || $s->quantity < 1) {
                    $this->fail('No tienes ese recurso.');
                }
                if (! ($r->properties['food'] ?? 0) && ! ($r->properties['water'] ?? 0)) {
                    $this->fail('Este objeto no se puede consumir.');
                }
                DB::table('player_inventory')->where('id', $s->id)->decrement('quantity');
                $p->hunger = max(0, $p->hunger - ($r->properties['food'] ?? 0));
                $p->thirst = max(0, $p->thirst - ($r->properties['water'] ?? 0));
                $result['title'] = 'Consumido: '.$r->name;
            } else {
                $this->fail('Acción desconocida.');
            }
            $result['health'] = $p->health - $initialHealth;
            $result['energy'] = $initialEnergy - $p->energy;
            $result['minutes'] = $w->minutes - $initialMinutes;
            $w->save();
            $p->save();
            $e = GameEvent::create(['world_id' => $w->id, 'player_id' => $p->id, 'location_id' => $l?->id, 'type' => $action, 'minute' => $w->minutes, 'result' => $result]);

            return ['event' => $e, ...app(WorldService::class)->state($w)];
        }, 3);
    }
}
