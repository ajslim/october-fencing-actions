<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetFencerVelocities extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:getfencervelocities';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    /**
     * Execute the console command.
     *
     * @return void
     * @throws \ImagickException
     */
    public function handle()
    {

        $actionQuery = Action::whereNotNull('id');

        if ($this->option('action_id') !== null) {
            $actionQuery->where('id', $this->option('action_id'));
        }

        $actions = $actionQuery->get();

        foreach ($actions as $action) {
            $command = "python3 " . getcwd() . '/../TrackPeople.py '
                . getcwd() . $action->video_url
                . ' --lightFrame=' . $action->light_frame_number
                . ' --display=0';

            $results = json_decode(exec(
                $command
            ));

            if ($results === null) {
                continue;
            }

            var_dump($results);
        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['action_id', null, InputOption::VALUE_OPTIONAL, 'The Action id', null],
        ];
    }
}
