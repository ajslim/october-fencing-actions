<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Fencer;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateFencersFromFie extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:updatefencersfromfie';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    private function makeRankingsUrl($weaponId, $genderId, $year, $pageNumber) {
        return "http://fie.org/results-statistic/ranking".
        "?result_models_Ranks%5BFencCatId%5D=S" .
        "&result_models_Ranks%5BWeaponId%5D=$weaponId" .
        "&result_models_Ranks%5BGenderId%5D=$genderId&" .
        "result_models_Ranks%5BCompTypeId%5D=I&" .
        "result_models_Ranks%5BCPYear%5D=$year&" .
        "result_models_Ranks%5BNationality%5D=&" .
        "result_models_Ranks_page=$pageNumber";
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
            $rankingFirstPage = Http::get($this->makeRankingsUrl($weapon, $gender, $yearString, "$currentPageNumber"));

            $dom = new DOMDocument();

            // The @ suppreses warnings from bad html
            @$dom->loadHTML($rankingFirstPage);

            $finder = new DomXPath($dom);
            $classname = "last";
            $lastPage = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

            // Get the total pages
            $totalPages = intval($lastPage->item(0)->textContent);

            do {
                $trs = $dom->getElementsByTagName('tr');
                foreach ($trs as $row) {
                    $tds = $row->getElementsByTagName('td');

                    if (sizeof($tds) > 0) {
                        $rank = intval($tds->item(0)->nodeValue);
                        echo $rank . " - ";

                        $fullName = $tds->item(2)->nodeValue;

                        $bothNames = $this->getFirstAndLastName($fullName);
                        $firstName = $bothNames[0];
                        $lastName = $bothNames[1];

                        echo $lastName . " " . $firstName . " - ";

                        $fieSiteLink = $tds->item(2)->getElementsByTagName('a')->item(0)->getAttribute('href');
                        $fieSiteNumber = substr(substr($fieSiteLink, strrpos($fieSiteLink, '-') + 1), 0, -1);
                        echo $fieSiteNumber . " - ";

                        $countryCode = $tds->item(3)->nodeValue;
                        echo $countryCode . " - ";

                        $birth = DateTime::createFromFormat('d.m.y', $tds->item(4)->nodeValue);

                        if (!$birth) {
                            // Fuck you SANGOWAWA BABATUNDE OLUFEMI, have a birthday like a normal person
                            continue;
                        }

                        echo $birth->format('Y-m-d');
                        echo "\n";

                        $fencer = Fencer::updateOrCreate(
                            ['fie_site_number' => $fieSiteNumber],
                            [
                                'last_name' => $lastName,
                                'first_name' => $firstName,
                                'country_code' => $countryCode,
                                'birth' => $birth,
                                'gender' => $gender, // Determined by list at top of function
                            ]
                        );
                        // Update the rank as we go
                        if (!$fencer->highest_rank || $rank < $fencer->highest_rank) {
                            $fencer->highest_rank = $rank;
                        }
                        $fencer->save();
                    }
                }

                $currentPageNumber += 1;
                $currentPage = Http::get($this->makeRankingsUrl($weapon, $gender, $year, "$currentPageNumber"));
                @$dom->loadHTML($currentPage);
            } while ($currentPageNumber <= $totalPages);
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
