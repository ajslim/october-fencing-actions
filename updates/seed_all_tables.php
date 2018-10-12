<?php namespace Ajslim\Fencingactions\Updates;

use Ajslim\FencingActions\Models\Call;
use Ajslim\FencingActions\Models\Tag;
use October\Rain\Database\Updates\Seeder;

class SeedAllTables extends Seeder
{
    public function run()
    {
        Call::create(['name' => 'Attack']);
        Call::create(['name' => 'Counter Attack']);
        Call::create(['name' => 'Riposte']);
        Call::create(['name' => 'Remise']);
        Call::create(['name' => 'Line']);
        Call::create(['name' => 'Unknown / Other']);

        Tag::create(['name' => 'In preparation']);
        Tag::create(['name' => 'Attack no - Attack']);
        Tag::create(['name' => 'Separating Attacks']);

    }
}