<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Resistenze',
                'slug' => 'resistenze',
                'icon' => 'heroicon-o-bolt',
                'color' => 'primary',
                'children' => [
                    ['name' => 'SMD', 'slug' => 'resistenze-smd'],
                    ['name' => 'THT', 'slug' => 'resistenze-tht'],
                    ['name' => 'Potenziometri', 'slug' => 'potenziometri'],
                ]
            ],
            [
                'name' => 'Condensatori',
                'slug' => 'condensatori',
                'icon' => 'heroicon-o-battery-100',
                'color' => 'success',
                'children' => [
                    ['name' => 'Ceramici', 'slug' => 'condensatori-ceramici'],
                    ['name' => 'Elettrolitici', 'slug' => 'condensatori-elettrolitici'],
                    ['name' => 'Tantalio', 'slug' => 'condensatori-tantalio'],
                ]
            ],
            [
                'name' => 'Semiconduttori',
                'slug' => 'semiconduttori',
                'icon' => 'heroicon-o-cpu-chip',
                'color' => 'warning',
                'children' => [
                    ['name' => 'Diodi', 'slug' => 'diodi'],
                    ['name' => 'Transistor', 'slug' => 'transistor'],
                    ['name' => 'IC', 'slug' => 'circuiti-integrati'],
                    ['name' => 'Microcontrollori', 'slug' => 'microcontrollori'],
                ]
            ],
            [
                'name' => 'Connettori',
                'slug' => 'connettori',
                'icon' => 'heroicon-o-link',
                'color' => 'info',
                'children' => [
                    ['name' => 'Pin Header', 'slug' => 'pin-header'],
                    ['name' => 'USB', 'slug' => 'connettori-usb'],
                    ['name' => 'Audio', 'slug' => 'connettori-audio'],
                ]
            ],
            [
                'name' => 'Alimentazione',
                'slug' => 'alimentazione',
                'icon' => 'heroicon-o-lightning-bolt',
                'color' => 'danger',
                'children' => [
                    ['name' => 'Regolatori', 'slug' => 'regolatori-tensione'],
                    ['name' => 'DC-DC', 'slug' => 'convertitori-dc-dc'],
                    ['name' => 'Batterie', 'slug' => 'batterie'],
                ]
            ],
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);
            
            $parent = Category::create($categoryData);
            
            foreach ($children as $index => $childData) {
                Category::create([
                    ...$childData,
                    'parent_id' => $parent->id,
                    'sort_order' => $index,
                ]);
            }
        }
    }
}