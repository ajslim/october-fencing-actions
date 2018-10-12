<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Fencer;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class GetAjslimGfycats extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:getAjslimGfycats';

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

        $gyfcatJson = Http::get("https://api.gfycat.com/v1/users/ajslim/albums/b6806a26a933057701f64ddcfbcf4987");

        $gfycats = json_decode($gyfcatJson, true);

        foreach($gfycats as $gfycatWrapper) {
            if(is_array($gfycatWrapper)) {
                foreach($gfycatWrapper as $gfycat) {
                    $gfycatId = $gfycat["gfyName"];
                    echo $gfycatId . "-";

                    $tags = [];
                    $priority = 0;
                    $gfyTags = $gfycat["tags"];

                    if(is_array($gfyTags)) {
                        foreach ($gfyTags as $gfyTag) {
                            if (
                                strtolower($gfyTag) == "point left"
                                || strtolower($gfyTag) == "off target left"
                            ) {
                                $priority = 1;
                            } else {
                                if (
                                    strtolower($gfyTag) == "point right"
                                    || strtolower($gfyTag) == "off target right"
                                ) {
                                    $priority = 2;
                                }
                            }

                            if (strtolower($gfyTag) == "separating attacks") {
                                $tags[] = "separating attacks"; // Separating attacks id
                            }
                        }
                    }

                    echo $priority;

                    echo "\n";

                    $action = Action::updateOrCreate(
                        ['gfycat_id' => $gfycatId],
                        [
                            'priority' => $priority,
                        ]
                    );
                    $action->save();
                }
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
        return [];
    }
}
