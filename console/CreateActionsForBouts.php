<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CreateActionsForBouts extends Command
{
    private $force = false;

    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:createactionsforbouts';

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
        if ($this->option('force') !== null) {
            $this->force = true;
        }

        $boutsQuery = Bout::whereNotNull('video_url');

        if ($this->option('tournamentId') !== null) {
            $boutsQuery->where('tournament_id', $this->option('tournamentId'));
        }

        $bouts = $boutsQuery->get();

        if ($this->option('deletePreviousVideoFolders') !== null) {
            $totalBouts = 0;
            foreach ($bouts as $bout) {
                $totalBouts += 1;

                echo "Total bouts: " . $totalBouts . "\n";

                $videoFolderName = getcwd() . '/storage/bout/' . substr($bout->video_url, 32) . "\n";

                echo 'deleting ' . $videoFolderName . "\n";

                system("rm -rf " . $videoFolderName, $result);

                echo $result . "\n";

                $actions = $bout->actions;

                foreach($actions as $action) {
                    $action->delete();
                }
            }
            return;
        }

        $totalBouts = 0;
        foreach ($bouts as $bout) {
            $totalBouts += 1;

            echo "Total bouts: " . $totalBouts . "\n";
            if ($totalBouts > 600) {
                echo "Stopping at 600 bouts - get a bigger server \n";
                break;
            }

            $this->call('fencingactions:createactionsforbout', ['bout-id' => $bout->id]);
        }
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
        return [
            ['force', null, InputOption::VALUE_OPTIONAL, 'create actions even if bout has actions', null],
            ['tournamentId', null, InputOption::VALUE_OPTIONAL, 'only create actions for bouts in certain tournament', null],
            ['deletePreviousVideoFolders', null, InputOption::VALUE_OPTIONAL, 'only create actions for bouts in certain tournament', null],
        ];
    }
}
