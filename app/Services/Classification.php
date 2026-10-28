<?php

namespace App\Services;

class Classification
{
    public static function categories(): array
    {
        return json_decode(<<<'JSON'
[{"id": "hospital", "name": "Hospital", "keys": ["amenity"], "values": ["hospital"], "group": "medical", "color": "#ee9494"}, {"id": "clinic", "name": "Centro médico", "keys": ["amenity"], "values": ["clinic", "doctors"], "group": "medical", "color": "#f0a7aa"}, {"id": "pharmacy", "name": "Farmacia", "keys": ["amenity"], "values": ["pharmacy"], "group": "medical", "color": "#8ac8b0"}, {"id": "supermarket", "name": "Supermercado", "keys": ["shop"], "values": ["supermarket"], "group": "food", "color": "#d8cb83"}, {"id": "grocery", "name": "Alimentación", "keys": ["shop"], "values": ["convenience", "greengrocer"], "group": "food", "color": "#d9ba78"}, {"id": "mall", "name": "Centro comercial", "keys": ["shop"], "values": ["mall"], "group": "equipment", "color": "#c9ad9b"}, {"id": "hardware", "name": "Ferretería", "keys": ["shop"], "values": ["hardware", "doityourself"], "group": "tools", "color": "#a5b6d0"}, {"id": "police", "name": "Comisaría", "keys": ["amenity"], "values": ["police"], "group": "combat", "color": "#df7d76"}, {"id": "fuel", "name": "Gasolinera", "keys": ["amenity"], "values": ["fuel"], "group": "fuel", "color": "#d4a466"}, {"id": "workshop", "name": "Taller", "keys": ["shop"], "values": ["car_repair"], "group": "tools", "color": "#a4b2cd"}, {"id": "warehouse", "name": "Almacén", "keys": ["building"], "values": ["warehouse"], "group": "materials", "color": "#a7b6a3"}, {"id": "restaurant", "name": "Restauración", "keys": ["amenity"], "values": ["restaurant", "cafe"], "group": "food", "color": "#ccaa7a"}, {"id": "industry", "name": "Industria", "keys": ["landuse", "building"], "values": ["industrial"], "group": "materials", "color": "#a5a8ad"}, {"id": "forest", "name": "Bosque", "keys": ["landuse", "natural"], "values": ["forest", "wood"], "group": "nature", "color": "#81b38a"}, {"id": "farmland", "name": "Campo agrícola", "keys": ["landuse"], "values": ["farmland"], "group": "nature", "color": "#b7b878"}, {"id": "residential", "name": "Zona residencial", "keys": ["landuse"], "values": ["residential"], "group": "equipment", "color": "#b9b3a9"}, {"id": "park", "name": "Parque", "keys": ["leisure"], "values": ["park"], "group": "nature", "color": "#99c99f"}, {"id": "water", "name": "Agua natural", "keys": ["natural", "waterway"], "values": ["water", "*"], "group": "nature", "color": "#7cb8d6"}]
JSON, true);
    }

    public function classify(array $tags): array
    {
        foreach (self::categories() as $r) {
            foreach ($r['keys'] as $key) {
                if (isset($tags[$key]) && (in_array($tags[$key], $r['values']) || in_array('*', $r['values']))) {
                    return ['category' => $r['id'], 'rule' => "$key={$tags[$key]}", 'confidence' => 1, 'kind' => in_array($key, ['landuse', 'natural', 'waterway', 'leisure']) ? 'zone' : 'poi'];
                }
            }
        }

        return ['category' => 'unknown', 'rule' => 'no-match', 'confidence' => 0, 'kind' => 'poi'];
    }
}
