<?php namespace Ajslim\Fencingactions\Updates;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Call;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\FencingActions\Models\Tag;
use Ajslim\Fencingactions\Models\Tournament;
use Ajslim\Fencingactions\Models\VoteComment;
use October\Rain\Database\Updates\Seeder;

class SeedVoteComments extends Seeder
{
    public function run()
    {
        VoteComment::create(['name' => 'No comment']);
        VoteComment::create(['name' => 'Not an action (clip doesn\'t have a halt, fencers only testing, etc.)']);
        VoteComment::create(['name' => 'Fencers listed on wrong sides']);
        VoteComment::create(['name' => 'Incorrect fencers listed']);
        VoteComment::create(['name' => 'Incorect bout details']);
    }
}