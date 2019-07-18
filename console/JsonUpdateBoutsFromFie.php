<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class JsonUpdateBoutsFromFie extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:jsonupdateboutsfromfie';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $temporaryTextArray;

    /**
     *
     * @param $fencerId
     * @param $season
     * @return stdClass
     */
    public function getBoutsJson($fencerId, $season): stdClass
    {
        $boutsRequest = [
            'athleteId' => $fencerId,
            'season' => $season
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fie.org/athlete/search');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($boutsRequest));
        $rankingFirstPage = curl_exec($ch);
        curl_close($ch);
        $result = json_decode("" . $rankingFirstPage);
        return $result;
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
            $json = $this->getBoutsJson($fencer->fie_site_number, 2019);

            $this->info($fencerIndex . '/' . $totalFencers . ' - ' . $fencer->fie_site_number);

            $bouts = $json->opponents;

            // Bouts
            foreach ($bouts as $bout) {

                // A bit untrue but tournament is uniquely defined as a place and a year
                $tournament = Tournament::where('fie_id', '=', $bout->competitionId)
                    ->where('year', '=', $bout->season)
                    ->first();

                $leftFencerName = $bout->fencer1->name;
                $bothNames = $this->getFirstAndLastName($leftFencerName);
                $leftFirstName = $bothNames[0];
                $leftLastName = $bothNames[1];

                $rightFencerName = $bout->fencer2->name;
                $bothNames = $this->getFirstAndLastName($rightFencerName);
                $rightFirstName = $bothNames[0];
                $rightLastName = $bothNames[1];

                $leftScore = $bout->fencer1->score;
                $rightScore = $bout->fencer2->score;

                $leftFencer = Fencer::where("fie_site_number", "=", $bout->fencer1->id)
                    ->first();

                $rightFencer = Fencer::where("fie_site_number", "=", $bout->fencer2->id)
                    ->first();

                if ($tournament !== null
                    && $leftFencer !== null
                    && $rightFencer !== null
                ) {
                    $this->displayTemporaryTextBlock(
                        [
                            $tournament->name,
                            "$tournament->name: $tournament->place: $tournament->weapon:$tournament->category:$tournament->type",
                            $tournament->start_date,
                            "$leftFirstName, $leftLastName: $leftScore",
                            "$rightFirstName, $rightLastName: $rightScore",
                        ]
                    );
                    $this->clearTemporaryTextBlock();

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
