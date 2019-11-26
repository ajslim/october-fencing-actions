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

class JsonUpdateFencersDetails extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:jsonupdatefencersdetails';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    private function makeRankingsRequest($weaponId, $genderId, $pageNumber) {

        return [
            "weapon" => $weaponId,
            "level" => "s",
            "type" => "i",
            "gender" => $genderId,
            "isTeam" => false,
            "isSearch" => false,
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
    public function getFencersPage($weapon, $gender, int $currentPageNumber): stdClass
    {
        $rankingsRequest = $this->makeRankingsRequest($weapon, $gender, "$currentPageNumber");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://fie.org/athletes/search');
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

        $currentPageNumber = 1;

        $page = $this->getFencersPage($weapon, $gender, $currentPageNumber);

        while (isset($page->allAthletes) !== false && count($page->allAthletes) > 0) {
            foreach ($page->allAthletes as $fencer) {

                $primaryWeapon = null;
                if (isset($fencer->weapon) === true) {
                    $primaryWeapon = strtolower($fencer->weapon);
                }
                echo $primaryWeapon . " - ";

                if ($primaryWeapon !== 'f') {
                    echo 'Not saving non-foilists' . "\n";
                    continue;
                }

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

                $height = $fencer->height;

                echo $height . " - ";

                $fieNumber = null;
                if (isset($fencer->licenseNumber) === true) {
                    $fieNumber = $fencer->licenseNumber;
                }
                echo $fieNumber . " - ";

                echo $birth->format('Y-m-d');


                $fencer = Fencer::where('fie_site_number', $fieSiteNumber)->first();

                if ($fencer === null) {
                    echo 'Fencer not found' . "\n";
                    continue;
                }

                $fencer->update(
                    [
                        'last_name' => $lastName,
                        'first_name' => $firstName,
                        'country_code' => $countryCode,
                        'birth' => $birth,
                        'gender' => $gender, // Determined by list at top of function
                        'hand' => $hand,
                        'height' => $height,
                        'fie_number' => $fieNumber,
                        'primary_weapon' => $primaryWeapon,
                    ]
                );

                $fencer->save();

            }
            $currentPageNumber += 1;
            $page = $this->getFencersPage($weapon, $gender, $currentPageNumber);
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
