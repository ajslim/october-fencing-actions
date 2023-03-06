<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use Ajslim\Fencingactions\Models\TournamentResult;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use stdClass;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\DB;

class GetTournamentData extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:gettournamentdata';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $temporaryTextArray;


    private static function findJson($text, $token) {
        $jsonStartPos = strpos($text, $token) + strlen($token);
        $jsonEndPosition = strpos($text, ';', $jsonStartPos);
        $length = $jsonEndPosition - $jsonStartPos;
        return substr($text, $jsonStartPos, $length);
    }


    /**
     *
     * @param $fencerId
     * @param $season
     * @return stdClass
     */
    public function getTournamentData($season, $tournamentId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://fie.org/competitions/$season/$tournamentId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);


        $object = new stdClass();
        $object->competition = json_decode(self::findJson($result, 'window._competition = '));
        $object->athletes = json_decode(self::findJson($result, 'window._athletes = '));
        $object->pools  = json_decode(self::findJson($result, 'window._pools = '));
        $object->poolsResults   = json_decode(self::findJson($result, 'window._poolsResults = '));

        return $object;
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

    public function handle()
    {
        $ids = [474, 111, 147, 160, 104, 138, 156, 98, 142, 163, 108, 137, 164, 385, 135];


        foreach ($ids as $id) {

            $this->getTournamnent(2018, $id);
        }
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function getTournamnent($season, $tournamentId)
    {
        echo "Getting $season $tournamentId\n";

        $tournamentData = $this->getTournamentData($season,$tournamentId);

        $cacheType = $tournamentData->competition->gender . $tournamentData->competition->weapon;

        foreach($tournamentData->athletes as $athlete) {
            echo "-------------------------\n";
            echo $athlete->fencer->name . "\n";
            echo "Fencer Id: " . $athlete->fencer->id . "\n";
            echo "FIE Ranking at start: " . $athlete->overallRanking . "\n";
            echo "Final Ranking: " . $athlete->rank . "\n";

            $tournamentResult = TournamentResult::updateOrCreate([
                'fencer_fie_site_number' => $athlete->fencer->id,
                'tournament_fie_id' => $tournamentId,
                'tournament_season' => $season,
            ],[
                'fie_ranking_at_start' => $athlete->overallRanking,
                'final_ranking' => $athlete->rank,
                'cache_name' => $athlete->fencer->name,
                'cache_tournament_type' => $cacheType
            ]);

            $tournamentResult->save();
        }

        $allRankedFencers = TournamentResult::where([
            'tournament_fie_id' => $tournamentId,
            'tournament_season' => $season,
        ])->whereNotNull('fie_ranking_at_start')
            ->orderBy('fie_ranking_at_start')->get();

        $lastInitialSeed = 1;
        foreach($allRankedFencers as $index => $fencer) {
            $fencer->initial_seed = $index + 1;
            echo $index + 1 . ':' . $fencer->cache_name . "\n";
            $fencer->save();
            $lastInitialSeed = $index + 1;
        }

        $allUnrankedFencers = TournamentResult::where([
            'tournament_fie_id' => $tournamentId,
            'tournament_season' => $season,
        ])->whereNull('fie_ranking_at_start')->get();

        foreach($allUnrankedFencers as $index => $fencer) {
            $fencer->initial_seed = $index + $lastInitialSeed + 1;
            echo $index + $lastInitialSeed + 1 . ':' . $fencer->cache_name . "\n";
            $fencer->save();
        }

        foreach($tournamentData->poolsResults as $poolsResult) {
            foreach($poolsResult as $row) {
                $tournamentResult = TournamentResult::updateOrCreate([
                    'fencer_fie_site_number' => $row->fencerId,
                    'tournament_fie_id' => $tournamentId,
                    'tournament_season' => $season,
                ],[
                    'pool_bouts_won' => $row->victory,
                    'pool_bouts_fought' => $row->matches,
                    'seed_after_pools' => $row->rank,
                    'pool_points_scored' => $row->td,
                    'pool_points_received' => $row->tr,
                ]);

                $tournamentResult->save();
            }
        }

        foreach($tournamentData->pools as $row) {
            foreach($row as $pool) {
                foreach($pool->rows as $fencerRow) {
                    $fencer = TournamentResult::where([
                        'tournament_fie_id' => $tournamentId,
                        'tournament_season' => $season,
                        'fencer_fie_site_number' => $fencerRow->fencerId
                    ])->first();

                    $fencer->pool_number = $pool->poolId;
                    $fencer->pool_size = count($pool->rows);
                    $fencer->save();
                }
            }
        }

        // Calculate rank_after_pools_with_byes
        $allTournamentFencers = DB::select(DB::raw(
            'select * from october_business.ajslim_fencingactions_tournament_results '
            . 'where seed_after_pools is not null '
            . 'and tournament_fie_id = ' . $tournamentId . ' '
            . 'and tournament_season = ' . $season . ' '
            . 'order by pool_bouts_fought - pool_bouts_won asc, '
            . 'pool_points_scored - pool_points_received desc, '
            . 'pool_points_scored desc'
        ));

        $tiedPlace = 0;
        $lastHash = '';
        foreach($allTournamentFencers as $index => $row) {
            $fencer = TournamentResult::find($row->id);
            $hash = $fencer->pool_bouts_fought . $fencer->pool_bouts_won . $fencer->pool_points_scored . $fencer->pool_points_received;

            if ($hash !== $lastHash) {
                $fencer->seed_after_pools_with_byes = $index + 1;
                $tiedPlace = $index + 1;
            } else {
                $fencer->seed_after_pools_with_byes = $tiedPlace;
            }
            $lastHash = $hash;
            $fencer->save();
        }

        $numberOfPools = TournamentResult::where([
            'tournament_fie_id' => $tournamentId,
            'tournament_season' => $season,
        ])->max('pool_number');

        for($poolNumber = 1; $poolNumber <= $numberOfPools; $poolNumber += 1) {
            $fencers = TournamentResult::where([
                'tournament_fie_id' => $tournamentId,
                'tournament_season' => $season,
                'pool_number' => $poolNumber
            ])->orderBy('initial_seed', 'asc')->get();

            foreach($fencers as $index => $fencer) {
                $seedInPool = $index + 1;
                $fencer->seed_in_pool = $seedInPool;
                $fencer->save();
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
        return [];
    }


    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
