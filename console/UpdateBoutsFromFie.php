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

    private $temporaryTextArray;


    private function makemakeHeadToHeadUrl($fencerLastName, $fencerFirstName, $fencerFieSiteNumber)
    {
        // Use the Last name of compound last names
        $fencerLastName = str_replace(' ', '-', $fencerLastName);
        $fencerLastNameArray = explode('-', $fencerLastName);
        $fencerLastName = $fencerLastNameArray[(count($fencerLastNameArray) - 1)];

        // Use the first name of compound first names (Though I think it actually doesn't matter what the first name is)
        $fencerFirstName = str_replace(' ', '-', $fencerFirstName);
        $fencerFirstNameArray = explode('-', $fencerFirstName);
        $fencerFirstName = $fencerFirstNameArray[0];

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
     * Displays a temporary text block
     *
     * @param array $lineArray The array of lines to display
     *
     * @return void
     */
    private function displayTemporaryTextBlock($lineArray)
    {
        $this->temporaryTextArray = $lineArray;
        foreach ($this->temporaryTextArray as $line) {
            echo $line . "\n";
        }
    }


    /**
     * Clears previous temporary text using "\033[F";
     *
     * @return void
     */
    private function clearTemporaryTextBlock()
    {
        // Return to top of block
        foreach ($this->temporaryTextArray as $line) {
            echo "\033[F";
        }

        // Clear text
        foreach ($this->temporaryTextArray as $line) {
            for ($i = 0; $i < strlen($line); $i++) {
                echo ' ';
            }
            echo "\n";
        }

        // Return to top of block
        foreach ($this->temporaryTextArray as $line) {
            echo "\033[F";
        }
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

        $gender = $this->argument('gender');
        $fencers = Fencer::where('gender', '=', $gender)->get();

        $lowestRank = $this->option('lowestRank');
        $gender = $this->argument('gender');
        if ($lowestRank !== null) {
            $fencers = Fencer::where(
                [
                    ['gender', '=', $gender],
                    ['highest_rank', '<=', intval($lowestRank)]
                ]
            )->get();
        } else {
            $fencers = Fencer::where('gender', '=', $gender)->get();
        }



        $totalFencers = count($fencers);
        foreach ($fencers as $fencerIndex => $fencer) {
            $currenUrl = $this->makemakeHeadToHeadUrl(
                $fencer->last_name,
                $fencer->first_name,
                $fencer->fie_site_number
            );
            $fencerBoutsPage = Http::get($currenUrl);

            $this->info($fencerIndex . '/' . $totalFencers . ' - ' . $currenUrl);

            $dom = new DOMDocument();

            // The @ suppreses warnings from bad html
            @$dom->loadHTML($fencerBoutsPage);

            $finder = new DomXPath($dom);

            $classname = "jumbotron__photo";
            $photoImgElement = $finder->query(
                "//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]"
            );
            if ($photoImgElement->item(0) !== null) {
                $photoUrl = $photoImgElement->item(0)->getAttribute('src');

                $this->info("updating photo");
                $fencer->photo_url = $photoUrl;
                $fencer->save();
            }

            $classname = "history__event-name";
            $historyEventNames = $finder->query(
                "//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]"
            );

            $classname = "history__table-row history__table-row_name_fencers";
            $historyFencerNames = $finder->query(
                "//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]"
            );

            $classname = "history__table-row history__table-row_name_score";
            $historyScores = $finder->query(
                "//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]"
            );

            // Bouts
            foreach ($historyEventNames as $index => $historyEvent) {
                $eventFullName = trim($historyEvent->nodeValue);

                $eventParts = explode(" ", $eventFullName);
                $eventPartsCount = count($eventParts);

                if ($eventPartsCount > 8) {
                    $eventWeapon = $eventParts[($eventPartsCount - 1)];
                    $eventCategory = $eventParts[($eventPartsCount - 3)];
                    $eventType = $eventParts[($eventPartsCount - 5)];
                    $eventDateString = $eventParts[($eventPartsCount - 7)];
                    $eventDate = DateTime::createFromFormat('Y-m-d', $eventDateString);

                    if (preg_match('!\(([^\)]+)\)!', $eventFullName, $match) === 1) {
                        $eventPlace = $match[1];
                    } else {
                        $this->error("Tournament place not recognised in - " . $eventFullName);
                    }

                    $eventNameParts = array_slice($eventParts, 0, ($eventPartsCount - 8));
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


                    $leftFencer = Fencer::where("first_name", "=", $leftFirstName)
                        ->where("last_name", "=", $leftLastName)
                        ->first();

                    $rightFencer = Fencer::where("first_name", "=", $rightFirstName)
                        ->where("last_name", "=", $rightLastName)
                        ->first();

                    $this->displayTemporaryTextBlock(
                        [
                            $eventFullName,
                            "$eventName: $eventPlace: $eventWeapon:$eventCategory:$eventType",
                            $eventDate->format('Y-m-d'),
                            "$leftFirstName, $leftLastName: $leftScore",
                            "$rightFirstName, $rightLastName: $rightScore",
                        ]
                    );

                    if ($tournament !== null
                        && $leftFencer !== null
                        && $rightFencer !== null
                    ) {
                        // Check to see that the reversed bout was not saved
                        // since you can't tell which side a fencer is on
                        // on the FIE site
                        $reversed = Bout::where(
                            [
                                'tournament_id' => $tournament->id,
                                'left_fencer_id' => $rightFencer->id,
                                'right_fencer_id' => $leftFencer->id,
                                'left_score' => $rightScore,
                                'right_score' => $leftScore,
                            ]
                        )->get();

                        if (count($reversed) === 0) {
                            $bout = Bout::updateOrCreate(
                                [
                                    'tournament_id' => $tournament->id,
                                    'left_fencer_id' => $leftFencer->id,
                                    'right_fencer_id' => $rightFencer->id,
                                    'left_score' => $leftScore,
                                    'right_score' => $rightScore,
                                ]
                            );

                            $name = $tournament->fullname . ': ' .
                                $leftFencer->last_name . " " . $leftFencer->first_name .
                                '-' .
                                $rightFencer->last_name . " " . $rightFencer->first_name;

                            $bout->cache_name = $name;

                            $bout->save();
                        }
                    }
                    $this->clearTemporaryTextBlock();
                }
            }
        }
    }


    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['gender', InputArgument::REQUIRED, 'The gender to get bouts from'],
        ];
    }


    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['lowestRank', null, InputOption::VALUE_OPTIONAL, 'The lowest rank fencer to consider.', null],
        ];
    }
}
