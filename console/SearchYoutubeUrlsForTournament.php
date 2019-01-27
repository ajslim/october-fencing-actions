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

class SearchYoutubeUrlsForTournament extends Command
{
    private $replace = false;
    private $acceptableUploaders = [];

    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:searchyoutubeurlsfortournament';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    private function makeYoutubeSearchUrl($year, $place, $fencer1, $fencer2) {

        return 'https://www.google.co.uk/search?q=youtube+fencing+$year+' .urlencode($place) . '+' . urlencode($fencer1) . '+' . urlencode($fencer2);
    }

    private function makeYoutubeApiSearchUrl($year, $place, $fencer1, $fencer2) {

        return 'https://www.googleapis.com/youtube/v3/search?part=snippet&q=fencing+$year+'
            . urlencode($place)
            . '+'
            . urlencode($fencer1)
            . '+'
            . urlencode($fencer2)
            . '&key='
            . ApiKey::$KEY;
    }


    private function makeYoutubeApiSearchUrlByName($year, $tournamentName, $fencer1, $fencer2) {

        return 'https://www.googleapis.com/youtube/v3/search?part=snippet&q=fencing+$year+'
            . urlencode($tournamentName)
            . '+'
            . urlencode($fencer1)
            . '+'
            . urlencode($fencer2)
            . '&key='
            . ApiKey::$KEY;
    }


    private function searchYoutubePage($bout)
    {

        $tournament = $bout->tournament;

        // Place might be 'Anaheim, California' we only want the first part
        $place = explode(',', $tournament->place)[0];

        $url = $this->makeYoutubeSearchUrl(
            $tournament->year,
            $place,
            $bout->left_fencer->last_name,
            $bout->right_fencer->last_name
        );

        $youtubeSearchPage = Http::get(
            $url
        );

        $dom = new DOMDocument();

        // The @ suppreses warnings from bad html
        @$dom->loadHTML($youtubeSearchPage);

        $finder = new DomXPath($dom);
        $classname = "g";
        $videos = $finder->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]");

        $url = '';
        foreach ($videos as $video) {
            $words = strtolower($video->nodeValue);

            $isAcceptableUploader = false;
            foreach ($this->acceptableUploaders as $uploader) {
                if (strpos($words, strtolower($uploader)) !== false) {
                    $isAcceptableUploader = true;
                    break;
                }
            }

            if ($isAcceptableUploader === true
                && strpos($words, $tournament->year) !== false
                && strpos($words, strtolower($place)) !== false
                && strpos($words, strtolower($bout->left_fencer->last_name)) !== false
                && strpos($words, strtolower($bout->right_fencer->last_name)) !== false
            ) {
                $anchors = $video->getElementsByTagName('a');

                // Find the anchor with the right url
                foreach ($anchors as $anchor) {
                    $url = $anchor->getAttribute('href');
                    if (strpos($url, '//www.youtube.com/') !== false) {
                        break;
                    }
                    $url = null;
                }

                if ($url !== null) {

                    // Weird double string
                    $start = strpos($url, '%3Fv%3D') + 7;
                    $videoUrl = 'https://www.youtube.com/watch?v=' . substr($url, $start, 11);
                    echo "$url\n";
                    echo "$videoUrl\n";

                    // Update the bout video URL
                    $bout->video_url = $videoUrl;
                    $bout->save();

                    // Don't need to look at the other videos
                    break;
                }
            }
        }
    }


    private function searchYoutubeApi($bout)
    {

        $boutFound = false;
        $tournament = $bout->tournament;

        // Place might be 'Anaheim, California' we only want the first part
        $place = explode(',', $tournament->place)[0];

        // Search by place and year
        $results = $this->searchApiByPlace($bout, $tournament, $place);
        $boutFound = $this->checkResultsAndSave($results['items'], $tournament, $bout, $place);

        // If not successful, then search by tournament name and year (e.g. CIP 2019)
        if ($boutFound === false) {
            $results = $this->searchApiByTournamentName($bout, $tournament);
            $boutFound = $this->checkResultsAndSave($results['items'], $tournament, $bout, $place);
        }
    }


    private function searchApiByPlace($bout, $tournament, $place)
    {



        $url = $this->makeYoutubeApiSearchUrl(
            $tournament->year,
            $place,
            $bout->left_fencer->last_name,
            $bout->right_fencer->last_name
        );

        return json_decode(file_get_contents($url), true);
    }


    private function searchApiByTournamentName($bout, $tournament)
    {
        $url = $this->makeYoutubeApiSearchUrlByName(
            $tournament->year,
            $tournament->name,
            $bout->left_fencer->last_name,
            $bout->right_fencer->last_name
        );

        return $results = json_decode(file_get_contents($url), true);
    }

    private function checkResultsAndSave($results, $tournament, $bout, $place)
    {
        $boutFound = false;
        foreach ($results as $result) {

            $uploader = $result['snippet']['channelTitle'];
            $title =  $result['snippet']['title'];

            if ($this->isCorrectBout($uploader, $title, $tournament, $bout, $place)) {
                $url = 'https://www.youtube.com/watch?v=' . $result['id']['videoId'];
                echo $url . "\n";

//                $bout->video_url = $url;
//                $bout->save();

                $boutFound = true;
                break;
            }
        }
        return $boutFound;
    }


    private function isCorrectBout($uploader, $title, $tournament, $bout, $place)
    {
        $words = strtolower($title);

        $isAcceptableUploader = in_array($uploader, $this->acceptableUploaders);

        $byPlace = $isAcceptableUploader === true
            && strpos($words, $tournament->year) !== false
            && strpos($words, strtolower($place)) !== false
            && strpos($words, strtolower($bout->left_fencer->last_name)) !== false
            && strpos($words, strtolower($bout->right_fencer->last_name)) !== false;

        $byName = $isAcceptableUploader === true
            && strpos($words, $tournament->year) !== false
            && strpos($words, strtolower($tournament->name)) !== false
            && strpos($words, strtolower($bout->left_fencer->last_name)) !== false
            && strpos($words, strtolower($bout->right_fencer->last_name)) !== false;

        return $byPlace || $byName;
    }


    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        if ($this->option('replace') !== null) {
            $this->replace = true;
        }


        $tournamentId = $this->argument('tournament-id');
        $tournament = Tournament::find($tournamentId);

        $bouts = $tournament->bouts;


        $this->acceptableUploaders = [
//            'Fencing Vision',
//            'USAFencing',
//            'FIE Fencing Channel',
//            'Great Foil Fencing',
            'Fédération Française d\'Escrime',
        ];

        foreach ($bouts as $bout) {

            if ($bout->video_url !== null && $this->replace !== true) {
                echo "Bout has video already \n";
                continue;
            }

            if ($bout->left_score <= 5 || $bout->left_score <= 5) {
                echo "Bout is likely a pool bout \n";
                continue;
            }

            echo "Searching for " . $bout->cache_name . "\n";

            $this->searchYoutubeApi($bout);
        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['tournament-id', InputArgument::REQUIRED, 'The tournament id'],
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['replace', null, InputOption::VALUE_OPTIONAL, 'Replace existing urls', null],
        ];
    }
}
