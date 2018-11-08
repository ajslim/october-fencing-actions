<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;

class Refresh extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:refresh';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {

        $this->call('plugin:refresh', ['name' => 'ajslim.fencingactions']);
        $this->call('fencingactions:updatefencersfromfie', ['startYear' => 2018, 'weapon' => 'f', 'gender' => 'm']);
        $this->call('fencingactions:updatetournamentsfromfie', ['weapon' => 'f', 'gender' => 'm']);
        $this->call('fencingactions:updateboutsfromfie', ['--lowestRank' => 5, 'gender' => 'm']);
        $this->call('fencingactions:searchyoutubeurlsfortournament', ['tournament-id' => 8]);
        $this->call('fencingactions:searchyoutubeurlsfortournament', ['tournament-id' => 18]);
        $this->call('fencingactions:createactionforbouts');

    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
