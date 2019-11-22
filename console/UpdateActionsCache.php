<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Action;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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

        $actions = Action::all()->random(3000);
        Log::info('Caching random 3000 action details');
        foreach ($actions as $action) {
            echo $action->id . ",";
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
