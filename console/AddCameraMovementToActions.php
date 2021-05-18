<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;


class AddCameraMovementToActions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:addcameratoactions';

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
        $actions = Action::all();

        $count = $actions->count();
        $current = 0;
        foreach ($actions as $action) {
            $current += 1;
            echo $action->video_url . "\n";
            echo $current . '/' . $count . "\n";
            $results = json_decode(exec( "python3 " . getcwd() . '/../Camera.py ' . getcwd() . $action->video_url));

            if ($results === null) {
                continue;
            }
            $action->camera_movement = json_encode($results->cameraMovement);
            $action->camera_displacement = $results->displacement;
            $action->camera_distance = $results->distance;
            $action->camera_frames_sampled = $results->frames;
            $action->save();
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
            ['bout_id', null, InputOption::VALUE_OPTIONAL, 'The Bout id', null],
        ];
    }
}
