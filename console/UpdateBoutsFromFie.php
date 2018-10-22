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

class UpdateBoutsFromFie extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:updateboutsfromfie';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    private function makemakeHeadToHeadUrl($fencerLastName, $fencerFirstName, $fencerFieSiteNumber) {

        return "http://fie.org/fencers/$fencerLastName-$fencerFirstName-$fencerFieSiteNumber/head-to-head";
    }

    private function getFirstAndLastName($fullName) {
        $allNames = explode(" ", $fullName);
        $lastName = "";
        $firstName = "";

        foreach($allNames as $name) {
            // Fie site lists last names as all caps (with hypens) and first names as lower case
            if (ctype_upper(str_replace("-", "", $name))) {
                $lastName .= $name . " ";
            } else {
                $firstName .= $name . " ";
            }
        }

        $lastName = trim($lastName);
        $firstName = trim($firstName);

        return [$firstName, $lastName];
    }

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $fencers = Fencer::all();

        foreach($fencers as $fencer) {
            $currenUrl = $this->makemakeHeadToHeadUrl($fencer->last_name, $fencer->first_name,
                $fencer->fie_site_number);
            $fencerBoutsPage = Http::get($currenUrl);

            echo $currenUrl . "\n";

            $dom = new DOMDocument();

            // The @ suppreses warnings from bad html
            @$dom->loadHTML($fencerBoutsPage);

            $finder = new DomXPath($dom);
            $classname = "history__event-name";
            $historyEventNames = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

            $classname = "history__table-row history__table-row_name_fencers";
            $historyFencerNames = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

            $classname = "history__table-row history__table-row_name_score";
            $historyScores = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

            // bouts
            foreach ($historyEventNames as $index => $historyEvent) {
                $eventFullName = trim($historyEvent->nodeValue);
                echo $eventFullName . "\n";

                $eventParts = explode(" ", $eventFullName);
                if (sizeof($eventParts) > 8) {
                    $eventWeapon = $eventParts[sizeof($eventParts) - 1];
                    $eventCategory = $eventParts[sizeof($eventParts) - 3];
                    $eventType = $eventParts[sizeof($eventParts) - 5];
                    $eventDateString = $eventParts[sizeof($eventParts) - 7];
                    $eventDate = DateTime::createFromFormat('Y-m-d', $eventDateString);

                    if (preg_match('!\(([^\)]+)\)!', $eventFullName, $match)) {
                        $eventPlace = $match[1];
                    }


                    $eventNameParts = array_slice($eventParts, 0, sizeof($eventParts) - 8);
                    $eventName = implode(" ", $eventNameParts);

                    $leftFencerName = trim($historyFencerNames->item($index)->childNodes->item(0)->nodeValue);
                    $bothNames = $this->getFirstAndLastName($leftFencerName);
                    $leftFirstName = $bothNames[0];
                    $leftLastName = $bothNames[1];

                    $rightFencerName = trim($historyFencerNames->item($index)->childNodes->item(2)->nodeValue);
                    $bothNames = $this->getFirstAndLastName($rightFencerName);
                    $rightFirstName = $bothNames[0];
                    $rightLastName = $bothNames[1];

                    $leftScore = trim($historyScores->item($index)->childNodes->item(0)->nodeValue);
                    $rightScore = trim($historyScores->item($index)->childNodes->item(2)->nodeValue);

                    // A bit untrue but tournament is uniquely defined as a place and a year
                    $tournament = Tournament::where('place', '=', $eventPlace)
                        ->where('year', '=', $eventDate->format("Y"))
                        ->where('weapon', '=', $eventWeapon)
                        ->where('category', '=', $eventCategory)
                        ->where('type', '=', $eventType)
                        ->first();

                    echo $tournament;
                    echo "\n";
                    echo "$eventName: $eventPlace: $eventWeapon:$eventCategory:$eventType \n";
                    echo $eventDate->format('Y-m-d');
                    echo "\n";
                    echo "$leftFirstName, $leftLastName: $leftScore\n";
                    $leftFencer = Fencer::where("first_name", "=", $leftFirstName)
                        ->where("last_name", "=", $leftLastName)
                        ->first();
                    echo "\n";
                    echo $leftFencer;
                    echo "\n";

                    echo "$rightFirstName, $rightLastName: $rightScore\n";
                    $rightFencer = Fencer::where("first_name", "=", $rightFirstName)
                        ->where("last_name", "=", $rightLastName)
                        ->first();
                    echo "\n";
                    echo $rightFencer;
                    echo "\n";

                    if ($tournament && $leftFencer && $rightFencer) {
                        $bout = Bout::updateOrCreate(
                            [
                                'tournament_id' => $tournament->id,
                                'left_fencer_id' => $leftFencer->id,
                                'right_fencer_id' => $rightFencer->id,
                                'left_score' => $leftScore,
                                'right_score' => $rightScore
                            ]
                        );
                        $bout->save();
                    }
                }
                echo "\n----------------------\n";
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