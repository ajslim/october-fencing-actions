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

class CalculateTournamentData extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:CalculateTournamentData';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $type = $this->option('type');
        $season = $this->option('season');
        $id = $this->option('id');

        $where = [];

        if ($type !== null) {
            $where['cache_tournament_type'] = $type;
        }
        if ($season !== null) {
            $where['tournament_season'] = $season;
        }
        if ($id !== null) {
            $where['tournament_fie_id'] = $id;
        }
        $query = TournamentResult::where($where);

        $tournamentCount = clone $query;
        $numberOfEvents = $tournamentCount->distinct('tournament_fie_id', 'tournament_season')->get(['tournament_fie_id', 'tournament_season']);

        echo count($numberOfEvents) . ' events' . "\n";
        foreach($numberOfEvents as $event) {
            echo $event->tournament_season . '-' . $event->tournament_fie_id . ', ';
        }
        echo "\n";


        $poolOfSixQuery = clone $query;
        $poolOfSixFencers = $poolOfSixQuery
            ->where(['pool_size' => 6])
            ->whereNotNull('seed_after_pools')->get();

        $total = 0;
        $totalWithByes = 0;
        forEach($poolOfSixFencers as $fencer) {
            $difference = $fencer->seed_after_pools - ($fencer->initial_seed - 16);
            $total += $difference;

            $differenceWithByes = $fencer->seed_after_pools_with_byes - ($fencer->initial_seed - 16);
            $totalWithByes += $differenceWithByes;
        }
        if (count($poolOfSixFencers) !== 0) {
            $average = $total / count($poolOfSixFencers);
            $averageWithByes = $totalWithByes / count($poolOfSixFencers);
            echo "Pool of six Average results difference: $average\n";
            echo "Pool of six Average results difference with byes: $averageWithByes\n";
        } else {
            echo "No Pools of 6\n";
        }

        $poolOfSevenQuery = clone $query;
        $poolOfSevenFencers = $poolOfSevenQuery
            ->where(['pool_size' => 7])
            ->whereNotNull('seed_after_pools')->get();


        $total = 0;
        $totalWithByes = 0;
        forEach($poolOfSevenFencers as $fencer) {
            $difference = $fencer->seed_after_pools - ($fencer->initial_seed - 16);
            $total += $difference;

            $differenceWithByes = $fencer->seed_after_pools_with_byes - ($fencer->initial_seed - 16);
            $totalWithByes += $differenceWithByes;
        }

        $average = $total/count($poolOfSevenFencers);
        $averageWithByes = $totalWithByes/count($poolOfSevenFencers);
        echo "Pool of Seven Average results difference: $average\n";
        echo "Pool of Seven Average results difference with Byes: $averageWithByes\n";

        $poolOfSixTotals = [];
        $poolOfSixWithByesTotals = [];
        $poolOfSixCounts = [];

        $poolOfSevenTotals = [];
        $poolOfSevenWithByesTotals = [];
        $poolOfSevenCounts = [];

        for($i = 1; $i <= 7; $i += 1) {
            $poolOfSixTotals[$i] = 0;
            $poolOfSixWithByesTotals[$i] = 0;
            $poolOfSixCounts[$i] = 0;

            $poolOfSevenTotals[$i] = 0;
            $poolOfSevenWithByesTotals[$i] = 0;
            $poolOfSevenCounts[$i] = 0;
        }

        for($i = 1; $i <= 7; $i += 1) {
            $poolOfSixQuery = clone $query;
            $poolOfSixFencers = $poolOfSixQuery
                ->where('pool_size', 6)
                ->where('seed_in_pool', $i)
                ->whereNotNull('seed_after_pools')->get();

            foreach ($poolOfSixFencers as $fencer) {
                $difference = $fencer->seed_after_pools - ($fencer->initial_seed - 16);
                $poolOfSixTotals[$i] += $difference;

                $differenceWithByes = $fencer->seed_after_pools_with_byes - ($fencer->initial_seed - 16);
                $poolOfSixWithByesTotals[$i] += $differenceWithByes;

                $poolOfSixCounts[$i] += 1;
            }

            $poolOfSevenQuery = clone $query;
            $poolOfSevenFencers = $poolOfSevenQuery
                ->where('pool_size', 7)
                ->where('seed_in_pool', $i)
                ->whereNotNull('seed_after_pools')->get();

            foreach ($poolOfSevenFencers as $fencer) {
                $difference = $fencer->seed_after_pools - ($fencer->initial_seed - 16);
                $poolOfSevenTotals[$i] += $difference;

                $differenceWithByes = $fencer->seed_after_pools_with_byes - ($fencer->initial_seed - 16);
                $poolOfSevenWithByesTotals[$i] += $differenceWithByes;

                $poolOfSevenCounts[$i] += 1;
            }
        }


        $columnLength = 20;
        echo str_pad("Seed in pool", $columnLength, " ", STR_PAD_RIGHT) . "|";
        echo str_pad("Pool of 6", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo str_pad("Pool of 7", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo str_pad("Difference", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo str_pad("Pool of 6 byes", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo str_pad("Pool of 7 byes", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo str_pad("Difference", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo str_pad("Overall", $columnLength, " ", STR_PAD_LEFT) . "|";
        echo "\n";
        for($i = 1; $i <= 7; $i += 1) {


            echo str_pad($i, $columnLength) . "|";

            if ($poolOfSixCounts[$i] !== 0) {
                echo str_pad(number_format(round($poolOfSixTotals[$i] / $poolOfSixCounts[$i], 1), 1), $columnLength, " ", STR_PAD_LEFT) . "|";
            } else {
                echo str_pad("", $columnLength, " ", STR_PAD_LEFT) . "|";
            }

            if ($poolOfSevenCounts[$i] !== 0) {
                echo str_pad(number_format(round($poolOfSevenTotals[$i] / $poolOfSevenCounts[$i], 1), 1), $columnLength, " ", STR_PAD_LEFT) . "|";
            }

            if ($poolOfSixCounts[$i] !== 0 && $poolOfSevenCounts[$i] !== 0) {
                $difference = ($poolOfSixTotals[$i] / $poolOfSixCounts[$i]) - ($poolOfSevenTotals[$i] / $poolOfSevenCounts[$i]);
                $output = number_format(round($difference, 1), 1);

                if ($difference < 0) {
                    $output .= ' for 6';
                } else {
                    $output .= ' for 7';
                }

                echo str_pad($output, $columnLength, " ", STR_PAD_LEFT) . "|";
            } else {
                echo str_pad("", $columnLength, " ", STR_PAD_LEFT) . "|";
            }


            if ($poolOfSixCounts[$i] !== 0) {
                echo str_pad(number_format(round($poolOfSixWithByesTotals[$i] / $poolOfSixCounts[$i], 1), 1), $columnLength, " ", STR_PAD_LEFT) . "|";
            } else {
                echo str_pad("", $columnLength, " ", STR_PAD_LEFT) . "|";
            }

            if ($poolOfSevenCounts[$i] !== 0) {
                echo str_pad(number_format(round($poolOfSevenWithByesTotals[$i] / $poolOfSevenCounts[$i], 1), 1), $columnLength, " ", STR_PAD_LEFT) . "|";
            }

            if ($poolOfSixCounts[$i] !== 0 && $poolOfSevenCounts[$i] !== 0) {
                $difference = ($poolOfSixWithByesTotals[$i] / $poolOfSixCounts[$i]) - ($poolOfSevenWithByesTotals[$i] / $poolOfSevenCounts[$i]);
                $output = number_format(round($difference, 1), 1);

                if ($difference < 0) {
                    $output .= ' for 6';
                } else {
                    $output .= ' for 7';
                }

                echo str_pad($output, $columnLength, " ", STR_PAD_LEFT) . "|";
            } else {
                echo str_pad("", $columnLength, " ", STR_PAD_LEFT) . "|";
            }

            if ($poolOfSixCounts[$i] !== 0 && $poolOfSevenCounts[$i] !== 0) {
                $difference = abs(($poolOfSixTotals[$i] / $poolOfSixCounts[$i]) - ($poolOfSevenTotals[$i] / $poolOfSevenCounts[$i])) - abs(($poolOfSixWithByesTotals[$i] / $poolOfSixCounts[$i]) - ($poolOfSevenWithByesTotals[$i] / $poolOfSevenCounts[$i]));
                $output = number_format(round($difference, 1), 1);
                echo str_pad($output, $columnLength, " ", STR_PAD_LEFT) . "|";
            } else {
                echo str_pad("", $columnLength, " ", STR_PAD_LEFT) . "|";
            }

            echo "\n";
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
        return [
            ['type', null, InputOption::VALUE_OPTIONAL, '', null],
            ['season', null, InputOption::VALUE_OPTIONAL, '', null],
            ['id', null, InputOption::VALUE_OPTIONAL, '', null],
        ];
    }
}
