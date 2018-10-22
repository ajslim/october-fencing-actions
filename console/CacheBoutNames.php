<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CacheBoutNames extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:cacheboutnames';

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

        $bouts = Bout::all();

        $totalBouts = count($bouts);
        foreach($bouts as $index=>$bout) {
            // Update the bouts name and cache it
            $tournament = Tournament::find($bout->tournament_id);
            $leftFencer = Fencer::find($bout->left_fencer_id);
            $rightFencer = Fencer::find($bout->right_fencer_id);

            $name = $tournament->fullname . ': ' .
                $leftFencer->last_name . " " . $leftFencer->first_name .
                '-' .
                $rightFencer->last_name . " " . $rightFencer->first_name;

            $bout->cache_name = $name;
            $bout->save();

            echo $index . "/" . $totalBouts . "\n";
            echo $bout->id . " - " . $name . "\n";
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
        return [];
    }
}
