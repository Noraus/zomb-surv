<?php

namespace App\Services;

class LootGenerator
{
    public static function catalog(): array
    {
        return json_decode(<<<'JSON'
[{"id": "water", "name": "Agua embotellada", "weight": 0.5, "group": "food", "rarity": "common", "properties": {"food": 0, "water": 25}}, {"id": "cans", "name": "Conservas", "weight": 0.4, "group": "food", "rarity": "uncommon", "properties": {"food": 25, "water": 0}}, {"id": "packed", "name": "Comida envasada", "weight": 0.3, "group": "food", "rarity": "uncommon", "properties": {"food": 20, "water": 0}}, {"id": "fresh", "name": "Alimentos frescos", "weight": 0.4, "group": "food", "rarity": "common", "properties": {"food": 18, "water": 3}}, {"id": "energy", "name": "Bebida energética", "weight": 0.3, "group": "food", "rarity": "uncommon", "properties": {"food": 5, "water": 15}}, {"id": "bandage", "name": "Vendas", "weight": 0.1, "group": "medical", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "medkit", "name": "Botiquín", "weight": 0.8, "group": "medical", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "painkiller", "name": "Analgésicos", "weight": 0.1, "group": "medical", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "antibiotic", "name": "Antibióticos", "weight": 0.1, "group": "medical", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "disinfectant", "name": "Desinfectante", "weight": 0.3, "group": "medical", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "instruments", "name": "Instrumental médico", "weight": 1, "group": "medical", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "hammer", "name": "Martillo", "weight": 0.8, "group": "tools", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "screwdriver", "name": "Destornillador", "weight": 0.2, "group": "tools", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "rope", "name": "Cuerda", "weight": 0.5, "group": "tools", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "toolbox", "name": "Caja de herramientas", "weight": 2, "group": "tools", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "torch", "name": "Linterna", "weight": 0.3, "group": "tools", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "battery", "name": "Baterías", "weight": 0.1, "group": "tools", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "wood", "name": "Madera", "weight": 1, "group": "materials", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "scrap", "name": "Chatarra", "weight": 0.6, "group": "materials", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "electronics", "name": "Componentes electrónicos", "weight": 0.2, "group": "materials", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "parts", "name": "Piezas mecánicas", "weight": 0.5, "group": "materials", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "textile", "name": "Material textil", "weight": 0.3, "group": "materials", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "gasoline", "name": "Gasolina", "weight": 0.8, "group": "fuel", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "diesel", "name": "Diésel", "weight": 0.85, "group": "fuel", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "oil", "name": "Aceite lubricante", "weight": 0.5, "group": "fuel", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "backpack", "name": "Mochila", "weight": 1, "group": "equipment", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "clothes", "name": "Ropa protectora", "weight": 1, "group": "equipment", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "helmet", "name": "Casco", "weight": 0.8, "group": "equipment", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "gloves", "name": "Guantes", "weight": 0.2, "group": "equipment", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "weapon", "name": "Arma improvisada", "weight": 1.5, "group": "combat", "rarity": "common", "properties": {"food": 0, "water": 0}}, {"id": "ammo", "name": "Munición ficticia", "weight": 0.1, "group": "combat", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}, {"id": "shield", "name": "Equipamiento defensivo", "weight": 2, "group": "combat", "rarity": "uncommon", "properties": {"food": 0, "water": 0}}]
JSON, true);
    }

    public function generate(string $id, string $category, string $seed, array $context = []): array
    {
        $rule = collect(Classification::categories())->firstWhere('id', $category);
        $group = $rule['group'] ?? 'materials';
        if ($group === 'nature') {
            $group = 'materials';
        }
        $result = [];
        foreach (self::catalog() as $r) {
            $h = hexdec(substr(hash('sha256', "$seed:$id:$category:{$r['id']}"), 0, 7));
            $primary = $r['group'] === $group;
            $environmental = in_array('industry', $context['zones'] ?? []) && $r['group'] === 'materials';
            if ($primary || $h % 13 === 0 || ($environmental && $h % 3 === 0)) {
                $scale = min(2, max(.5, (float) ($context['size'] ?? 1))) * max(.1, (float) ($context['abundance'] ?? config('survival.abundance')));
                $result[$r['id']] = max(1, (int) floor(($primary ? 2 + $h % 5 : 1) * $scale));
            }
        }

        return $result;
    }
}
