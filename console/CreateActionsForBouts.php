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
    protected $name = 'fencingactions:createactionforbouts';

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


        $bouts = Bout::whereNotNull('video_url')->get();


        $totalBouts = 0;
        foreach ($bouts as $bout) {

            $totalBouts += 1;

            echo "Total bouts: " . $totalBouts . "\n";
            if ($totalBouts > 80) {
                echo "Stopping at 80 bouts - get a bigger server \n";
                break;
            }

            if ($bout->actions()->count() > 0 && $this->force !== true) {
                echo $bout->cache_name . " already has actions \n";
                continue;
            }

            echo "Creating actions for " . $bout->cache_name . "\n";


            $url = $bout->video_url;
            parse_str( parse_url( $url, PHP_URL_QUERY ), $parameters );

            // Continue if bout is missing youtube id
            if (isset($parameters['v']) !== true) {
                continue;
            }

            $this->videoId = $parameters['v'];
            $folder = "/storage/bout/" . $this->videoId;

            // If the clips don't exist, analyse the bout
            if (file_exists(getcwd() . $folder) !== true) {
                $this->call('fencingactions:analyzebout', ['url' => $url]);
            }

            if (file_exists(getcwd() . $folder . '/clips') !== true) {
                echo 'can not find ' . getcwd() . $folder . '/clips' . "\n";
                echo "Clips folder not created, something went wrong\n";
                continue;
            }

            echo "Updating or creating actions for bout:" . $bout->id . "\n";
            $files = glob(getcwd() . $folder . '/clips/*'); // get all file names
            foreach($files as $file){ // iterate files
                $pathParts = pathinfo($file);

                echo "Creating action for $file \n";

                $action = Action::updateOrCreate(
                    [
                        'bout_id' => $bout->id,
                        'video_url' => $folder . '/clips/' . $pathParts['filename'] . '.mp4',
                        'thumb_url' => $folder . '/lightthumbs/' . $pathParts['filename'] . '.png',
                    ]
                );
                $action->time = (integer) $pathParts['filename'];
                $action->save();
            }
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
        ];
    }
}
