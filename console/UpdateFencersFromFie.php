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

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $currentPageNumber = 1;
        $mensFoilRankingFirstPage = Http::get($this->makeRankingsUrl('f', 'm', '2019', "$currentPageNumber"));

        $dom = new DOMDocument();

        // The @ suppreses warnings from bad html
        @$dom->loadHTML($mensFoilRankingFirstPage);

        $finder = new DomXPath($dom);
        $classname="last";
        $lastPage = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

        // Get the total pages
        $totalPages = intval($lastPage->item(0)->textContent);

        do {
            $trs = $dom->getElementsByTagName('tr');
            foreach ($trs as $row) {
                $tds = $row->getElementsByTagName('td');

                if (sizeof($tds) > 0) {


                    $name = $tds->item(2)->nodeValue;
                    echo $name . " - ";

                    $fieSiteLink = $tds->item(2)->getElementsByTagName('a')->item(0)->getAttribute('href');
                    $fieSiteNumber = substr(substr($fieSiteLink, strrpos($fieSiteLink, '-') + 1), 0, -1);
                    echo $fieSiteNumber . " - ";

                    $countryCode = $tds->item(3)->nodeValue;
                    echo $countryCode . " - ";

                    $birth = DateTime::createFromFormat('d.m.y', $tds->item(4)->nodeValue);
                    echo $birth->format('Y-m-d');
                    echo "\n";

                    $fencer = Fencer::updateOrCreate(
                        ['fie_site_number' => $fieSiteNumber],
                        [
                            'name' => $name,
                            'country_code' => $countryCode,
                            'birth' => $birth,
                        ]
                    );
                    $fencer->save();
                }
            }

            $currentPageNumber += 1;
            $currentPage = Http::get($this->makeRankingsUrl('f', 'm', '2019', "$currentPageNumber"));
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
