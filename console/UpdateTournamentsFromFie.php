<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateTournamentsFromFie extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:updatetournamentsfromfie';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    private function makeTournamentsUrl($weaponId, $genderId, $pageNumber) {

        return "http://fie.org/results-statistic/result" .
        "?calendar_models_CalendarsCompetition%5BFencCatId%5D=S" .
        "&calendar_models_CalendarsCompetition%5BWeaponId%5D=$weaponId" .
        "&calendar_models_CalendarsCompetition%5BGenderId%5D=$genderId" .
        "&calendar_models_CalendarsCompetition%5BCompTypeId%5D=I" .
        "&calendar_models_CalendarsCompetition%5BCompCatId%5D=" .
        "&calendar_models_CalendarsCompetition%5BCPYear%5D=" .
        "&calendar_models_CalendarsCompetition%5BFedId%5D=" .
        "&calendar_models_CalendarsCompetition%5BDateBegin%5D=&" .
        "calendar_models_CalendarsCompetition%5BDateEnd%5D=" .
        "&calendar_models_CalendarsCompetition_page=$pageNumber";
    }

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $currentPageNumber = 1;
        $currenUrl = $this->makeTournamentsUrl('f', 'm', "$currentPageNumber");
        $mensFoiltournamentFirstPage = Http::get($currenUrl);

        $dom = new DOMDocument();

        // The @ suppreses warnings from bad html
        @$dom->loadHTML($mensFoiltournamentFirstPage);

        $finder = new DomXPath($dom);
        $classname="last";
        $lastPage = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

        // Get the total pages
        $totalPages = intval($lastPage->item(0)->textContent);

        do {
            echo $currenUrl . "\n ------------------------------- \n";
            $trs = $dom->getElementsByTagName('tr');
            foreach ($trs as $row) {
                $tds = $row->getElementsByTagName('td');

                if (sizeof($tds) > 0) {


                    $name = $tds->item(0)->nodeValue;
                    echo $name . " - ";

                    $fieSiteLink = $tds->item(0)->getElementsByTagName('a')->item(0)->getAttribute('href');
                    $fieId = explode('/', $fieSiteLink)[3];
                    $year = explode('/', $fieSiteLink)[2];
                    echo $year . ":" . $fieId . " - ";

                    $place = $tds->item(1)->nodeValue;
                    echo $place . " - ";

                    $countryCode = $tds->item(2)->nodeValue;
                    echo $countryCode . " - ";

                    $startDate = DateTime::createFromFormat('d.m.y', $tds->item(3)->nodeValue);
                    if ($startDate) {
                        $startDate->setDate($year, (int)$startDate->format('m'), (int)$startDate->format('d'));
                        echo $startDate->format('Y-m-d') . " - ";
                    } else {
                        echo "none";
                    }

                    $endDate = DateTime::createFromFormat('d.m.y', $tds->item(4)->nodeValue);
                    if ($endDate) {
                        $endDate->setDate($year, (int)$endDate->format('m'), (int)$endDate->format('d'));
                        echo $endDate->format('Y-m-d') . " - ";
                    } else {
                        echo "none";
                    }

                    $weapon = $tds->item(5)->nodeValue;
                    echo $weapon . " - ";

                    $gender = $tds->item(6)->nodeValue;
                    echo $gender . " - ";

                    $category = $tds->item(7)->nodeValue;
                    echo $category . " - ";

                    $type = $tds->item(8)->nodeValue;
                    echo $type . " - ";

                    $event = $tds->item(9)->nodeValue;
                    echo $type . " - ";

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
            }

            $currentPageNumber += 1;
            $currenUrl = $this->makeTournamentsUrl('f', 'm',"$currentPageNumber");
            $currentPage = Http::get($currenUrl);
            @$dom->loadHTML($currentPage);
        } while ($currentPageNumber <= $totalPages);


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
