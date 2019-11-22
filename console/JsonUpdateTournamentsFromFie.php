<?php namespace Ajslim\FencingActions\Console;

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

class JsonUpdateTournamentsFromFie extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:jsonupdatetournamentsfromfie';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    /**
     *
     * @param $fencerId
     * @param $season
     *
     * @return stdClass | null
     */
    public function getTournamentsJson($weaponId, $genderId, $category, $pageNumber)
    {
        $tournamentsRequest = [
            'status' => 'passed',
            'weapon' => [$weaponId],
            'gender' => [$genderId],
            'type' => ['i'],
            'season' => '-1',
            'level' => 's',
            'competitionCategory' => $category,
            'fromDate' => '',
            'toDate' => '',
            'fetchPage' => $pageNumber
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fie.org/competitions/search');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($tournamentsRequest));
        $rankingFirstPage = curl_exec($ch);
        curl_close($ch);
        $result = json_decode("" . $rankingFirstPage);

        return $result;
    }



    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $weapon = $this->argument('weapon');
        $gender = $this->argument('gender');


        $categories = [
          'JO',
          'CHM',
          'GP',
          'A',
          'SA',
          'CHZ'
        ];

        foreach ($categories as $category) {
            echo $category;

            $pageNumber = 1;
            $apiResponse = $this->getTournamentsJson($weapon, $gender, $category, $pageNumber);

            if ($apiResponse === null) {
                echo ' not found' . "\n";
                continue;
            }

            echo "\n";

            $numberOfPages = ceil($apiResponse->totalFound / $apiResponse->pageSize);

            $tournamentsResponses = $apiResponse->items;


            do {
                echo "Page $pageNumber / $numberOfPages \n";

                foreach($tournamentsResponses as $tournamentResponse) {
                    $name = $tournamentResponse->name;
                    echo $name . " - ";


                    $fieId = $tournamentResponse->competitionId;
                    $year = $tournamentResponse->season;
                    echo $year . ":" . $fieId . " - ";

                    $place = $tournamentResponse->location;
                    echo $place . " - ";

                    $countryCode = $tournamentResponse->federation;
                    echo $countryCode . " - ";

                    $startDate = DateTime::createFromFormat('d-m-Y', $tournamentResponse->startDate);
                    echo $startDate->format('Y-m-d');

                    $endDate = DateTime::createFromFormat('d-m-Y', $tournamentResponse->endDate);
                    echo $endDate->format('Y-m-d');

                    $weapon = strtoupper(substr($tournamentResponse->weapon, 0, 1));
                    echo $weapon . " - ";

                    $gender = strtoupper(substr($tournamentResponse->gender, 0, 1));
                    echo $gender . " - ";

                    $category = strtoupper(substr($tournamentResponse->category, 0, 1));
                    echo $category . " - ";

                    $type = $category;
                    echo $type . " - ";

                    $event = strtoupper(substr($tournamentResponse->type, 0, 1));
                    echo $event . " - ";

                    echo "\n";

                        $tournament = Tournament::updateOrCreate(
                            [
                                'year' => $year,
                                'fie_id' => $fieId
                            ],
                            [
                                'name' => $name,
                                'place' => $place,
                                'country_code' => $countryCode,
                                'weapon' => $weapon,
                                'category' => $category,
                                'gender' => $gender,
                                'type' => $type,
                                'event' => $event,
                            ]
                        );

                        if ($startDate) {
                            $tournament->start_date = $startDate;
                        }

                        if ($endDate) {
                            $tournament->end_date = $endDate;
                        }

                        $tournament->save();

                    }

                $pageNumber += 1;
                $apiResponse = $this->getTournamentsJson($weapon, $gender, $category, $pageNumber);
                $tournamentsResponses = $apiResponse->items;
            } while ($pageNumber <= $numberOfPages);
        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['weapon', InputArgument::REQUIRED, 'The weapon to get fencers from'],
            ['gender', InputArgument::REQUIRED, 'The gender to get fencers from'],
        ];
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
