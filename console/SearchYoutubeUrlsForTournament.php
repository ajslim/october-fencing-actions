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

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $tournamentId = $this->argument('tournament-id');
        $tournament = Tournament::find($tournamentId);

        $bouts = $tournament->bouts;


        $acceptableUploaders = [
            'Fencing Vision',
            'USAFencing',
            'FIE Fencing Channel',
            'Great Foil Fencing',
        ];

        foreach ($bouts as $bout) {

            echo "Searching for " . $bout->cache_name . "\n";

            $url = $this->makeYoutubeSearchUrl(
                $tournament->year,
                $tournament->place,
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
                foreach ($acceptableUploaders as $uploader) {
                    if (strpos($words, strtolower($uploader)) !== false) {
                        $isAcceptableUploader = true;
                        break;
                    }
                }

                if ($isAcceptableUploader === true
                    && strpos($words, $tournament->year) !== false
                    && strpos($words, strtolower($tournament->place)) !== false
                    && strpos($words, strtolower($bout->left_fencer->last_name)) !== false
                    && strpos($words, strtolower($bout->right_fencer->last_name)) !== false
                ) {
                    $anchors = $video->getElementsByTagName('a');
                    if ($anchors->item(1) !== null) {

                        // Weird double string
                        $url = $anchors->item(1)->getAttribute('href');
                        $start = strpos($url, '%3Fv%3D') + 7;
                        $videoUrl = 'https://www.youtube.com/watch?v=' . substr($url, $start, 11);
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
        return [];
    }
}
