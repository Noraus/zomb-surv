<?php

return [
    'seed' => env('WORLD_SEED', 'donostia-2031'), 'abundance' => (float) env('WORLD_ABUNDANCE', 1),
    'risk' => (float) env('WORLD_RISK', 1), 'travel_multiplier' => (float) env('TRAVEL_MULTIPLIER', 1.3),
    'capacity' => (float) env('PLAYER_CAPACITY', 20),
    'map_style' => env('MAP_STYLE', 'https://tiles.openfreemap.org/styles/liberty'),
    'overpass' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
    'risk_by_category' => ['hospital' => 55, 'clinic' => 30, 'pharmacy' => 25, 'supermarket' => 45, 'grocery' => 30, 'mall' => 60, 'hardware' => 35, 'police' => 65, 'fuel' => 50, 'workshop' => 35, 'warehouse' => 40, 'restaurant' => 35, 'industry' => 45, 'forest' => 10, 'farmland' => 15, 'residential' => 40, 'park' => 20, 'water' => 15],
    'scavenge' => ['minutes' => 15, 'energy' => 8, 'injury_damage' => 3, 'extra_energy' => 4, 'visit_risk' => 3, 'maximum_risk' => 90],
];
