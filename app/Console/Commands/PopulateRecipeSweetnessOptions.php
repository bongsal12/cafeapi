<?php

namespace App\Console\Commands;

use App\Models\InventoryRecipe;
use App\Models\InventoryItem;
use Illuminate\Console\Command;

class PopulateRecipeSweetnessOptions extends Command
{
    protected $signature = 'recipes:populate-sweetness-options';
    protected $description = 'Populate sweetness_options for recipes that have a sugar ingredient';

    public function handle()
    {
        $sugar = InventoryItem::query()
            ->where(function ($q) {
                $q->where('category', 'ILIKE', '%sweetener%')
                  ->orWhere('name', 'ILIKE', '%sugar%');
            })
            ->first();

        if (!$sugar) {
            $this->error('No sugar ingredient found');
            return 1;
        }

        $recipes = InventoryRecipe::query()
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($recipes as $recipe) {
            // Populate sweetness_options if missing or empty
            if ($recipe->sweetness_options === null || empty($recipe->sweetness_options)) {
                $recipe->update([
                    'sweetness_options' => [
                        ['level' => 'No sweet', 'inventory_item_id' => $sugar->id, 'quantity' => 0],
                        ['level' => 'Less', 'inventory_item_id' => $sugar->id, 'quantity' => 2.5],
                        ['level' => 'Normal', 'inventory_item_id' => $sugar->id, 'quantity' => 5],
                        ['level' => 'More', 'inventory_item_id' => $sugar->id, 'quantity' => 7.5],
                    ],
                ]);
                $count++;
                $this->line("✓ Updated recipe #{$recipe->id}: {$recipe->product?->name} ({$recipe->size})");
            }
        }

        $this->info("Populated sweetness_options for {$count} recipes");
        return 0;
    }
}
