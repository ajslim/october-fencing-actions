<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Fencer;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class JsonUpdateFencersFromFie extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:jsonupdatefencersfromfie';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    private function makeRankingsRequest($weaponId, $genderId, $year, $pageNumber) {

        return [
            "weapon" => $weaponId,
            "level" => "s",
            "type" => "i",
            "gender" => $genderId,
            "isTeam" => false,
            "isSearch" => false,
            "season" => $year,
            "name" => "",
            "country" => "",
            "fetchPage" => $pageNumber,
        ];
    }

    /**
     * @param $weapon
     * @param $gender
     * @param string $yearString
     * @param int $currentPageNumber
     *
     * @return stdClass
     */
    public function getFencersPage($weapon, $gender, string $yearString, int $currentPageNumber): stdClass
    {
        $rankingsRequest = $this->makeRankingsRequest($weapon, $gender, $yearString, "$currentPageNumber");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fie.org/athletes');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($rankingsRequest));
        $rankingFirstPage = curl_exec($ch);
        curl_close($ch);
        $fencers = json_decode("" . $rankingFirstPage);
        return $fencers;
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
        $weapon = $this->argument('weapon');
        $gender = $this->argument('gender');

        $currentYear = intval(date('Y'));
        $startYear = $this->argument('startYear');

        for($year = $startYear; $year <= $currentYear; $year += 1) {
            $yearString = strval($year);

            echo "$yearString - $weapon - $gender \n";

            $currentPageNumber = 1;


            $page = $this->getFencersPage($weapon, $gender, $yearString, $currentPageNumber);
            while (isset($page->allAthletes) !== false && count($page->allAthletes) > 0) {
                foreach ($page->allAthletes as $fencer) {
                    echo "\n";
                    $rank = $fencer->rank;
                    echo $rank . " - ";

                    $fullName = $fencer->name;
                    $bothNames = $this->getFirstAndLastName($fullName);
                    $firstName = $bothNames[0];
                    $lastName = $bothNames[1];

                    echo $lastName . " " . $firstName . " - ";

                    $fieSiteNumber = $fencer->id;
                    echo $fieSiteNumber . " - ";

                    $countryCode = $fencer->country;
                    echo $countryCode . " - ";

                    $birth = DateTime::createFromFormat('Y-m-d', $fencer->date);
                    if (!$birth) {
                        // Fuck you SANGOWAWA BABATUNDE OLUFEMI, have a birthday like a normal person
                        continue;
                    }

                    $hand = $fencer->hand;

                    echo $hand . " - ";

                    echo $birth->format('Y-m-d');

                    $fencer = Fencer::updateOrCreate(
                        ['fie_site_number' => $fieSiteNumber],
                        [
                            'last_name' => $lastName,
                            'first_name' => $firstName,
                            'country_code' => $countryCode,
                            'birth' => $birth,
                            'gender' => $gender, // Determined by list at top of function
                            'hand' => $hand
                        ]
                    );

                    // Update the rank as we go
                    if (!$fencer->highest_rank || $rank < $fencer->highest_rank) {
                        echo " - highest rank changed: " . $rank;

                        $fencer->highest_rank = $rank;

                        // Their primary weapon is their best ranked weapon
                        $fencer->primary_weapon = $weapon;
                    }
                    $fencer->save();

                }
                $currentPageNumber += 1;
                $page = $this->getFencersPage($weapon, $gender, $yearString, $currentPageNumber);
            }
        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['startYear', InputArgument::REQUIRED, 'The year to start searching from'],
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
