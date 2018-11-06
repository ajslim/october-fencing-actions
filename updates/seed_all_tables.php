<?php namespace Ajslim\Fencingactions\Updates;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Call;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\FencingActions\Models\Tag;
use Ajslim\Fencingactions\Models\Tournament;
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

//        Fencer::create([
//            'first_name' => 'Alessio',
//            'last_name' => 'Foconi',
//            'fie_site_number' => '15860',
//            'country_code' => 'ITA',
//            'gender' => 'M'
//        ]);
//
//        Fencer::create([
//            'first_name' => 'Richard',
//            'last_name' => 'Kruse',
//            'fie_site_number' => '3256',
//            'country_code' => 'ITA',
//            'gender' => 'M'
//        ]);
//
//        Tournament::create([
//            'name' => '2018 World Championships',
//            'year' => '2018',
//            'start_date' => '2018-07-21',
//            'end_date' => '2018-07-24',
//            'fie_id' => '244',
//            'country_code' => 'CHN',
//            'place' => 'Wuxi',
//            'weapon' => 'F',
//            'gender' => 'M',
//            'category' => 'S',
//            'type' => 'CHM',
//            'event' => 'I',
//        ]);
//
//        Bout::create([
//            'left_fencer_id' => 2,
//            'right_fencer_id' => 1,
//            'left_score' => 8,
//            'right_score' => 15,
//            'tournament_id' => 1,
//            'cache_name' => '2018 mens foil World Championship Final'
//        ]);
    }
}