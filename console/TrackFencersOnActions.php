<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;


class TrackFencersOnActions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:trackfencersonactions';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $actionId = $this->option('action-id');
        $maxId = $this->option('max-id');
        $minId = $this->option('min-id');

        if ($actionId !== null) {
            $actions = Action::where('id', $actionId)->get();
        } else {
            $actions = Action::where('id', '>', $minId)
                ->where('id', '<', $maxId)
                ->get();
        }

        $count = $actions->count();
        $current = 0;
        $failed = [];
        foreach ($actions as $action) {
            $current += 1;
            echo $action->video_url . "\n";
            echo $current . '/' . $count . "\n";
            $command = "python3 " . getcwd() . '/../yolo/main.py ' . getcwd() . $action->video_url;
            $results = exec($command);

            if ($results === null) {
                continue;
            }


            echo $command . "\n";
            echo '--------------------------------' . "\n";

            echo $results;

            echo '--------------------------------' . "\n";

            if ($results !== '') {
                $action->fencer_movement = $results;
                $action->save();
            } else {
                $failed[] = $command;
            }
        }

        foreach($failed as $command) {
            echo $command . "\n";
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
            ['action-id', null, InputOption::VALUE_OPTIONAL, 'The Action id', null],
            ['max-id', null, InputOption::VALUE_OPTIONAL, 'The Action id to stop at', 100000],
            ['min-id', null, InputOption::VALUE_OPTIONAL, 'The Action id to start at', 0],
        ];
    }
}
