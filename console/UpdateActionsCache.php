<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use Ajslim\Fencingactions\Models\Vote;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use October\Rain\Network\Http;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateActionsCache extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:updateactionscache';

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
        $actions = Action::all();

        foreach ($actions as $action) {
            echo $action->id . ",";

            $combinedFile = str_replace('clips', 'combined', $action->video_url);
            if (file_exists(getcwd() . $combinedFile)) {
                echo 'Combined Audio file exists' . "\n";
                $action->video_url = $combinedFile;
            }

            $action->updateCacheColumns();
        }
        Log::info('Finished Caching');
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
