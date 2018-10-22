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

class RemoveDuplicateBouts extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:removeduplicatebouts';

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
        $originals = [];
        $duplicates = [];

        $totalBouts = count($bouts);
        foreach ($bouts as $index=>$bout) {
            if (!isset($duplicates[$bout->id])) {
                $originals[$bout->id] = $bout;
                $duplicate = Bout::where(
                    [
                        ['left_fencer_id', '=', $bout->right_fencer_id],
                        ['right_fencer_id', '=', $bout->left_fencer_id],
                        ['left_score', '=', $bout->right_score],
                        ['right_score', '=', $bout->left_score],
                        ['tournament_id', '=', $bout->tournament_id],
                    ]
                )->first();

                if ($duplicate) {
                    // Remove the actions from the duplicate and place them on the original
                    $duplicate->actions()->update(['bout_id' => $bout->id]);

                    // Add duplicate to duplicates array
                    $duplicates[$duplicate->id] = $duplicate;
                }
            }

            echo $index . '/' . $totalBouts . "\r";
        }

        echo count($originals) . ' - ' . count($duplicates) . "\n";

        $totalDuplicates = count($duplicates);
        foreach ($duplicates as $index=>$duplicate) {
            echo $index . '/' . $totalDuplicates . "\r";
            $duplicate->delete();
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
