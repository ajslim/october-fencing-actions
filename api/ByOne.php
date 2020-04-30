<?php
/**
 * API.php
 * The api json frontend controller
 */

namespace Ajslim\FencingActions\Api;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Call;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use Backend\Classes\Controller;
use DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class ByOne extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index(
        $fencerId = null
    ) {
        if ($fencerId !== null) {
            return $this->makeByOneBoutsResponse($fencerId);
        }

        return $this->makeByOneResponse();
    }


    /**
     * The index controller
     *
     * @return array
     */
    public function indexDe(
        $fencerId = null
    ) {
        if ($fencerId !== null) {
            return $this->makeByOneDeBoutsResponse($fencerId);
        }

        return $this->makeByOneDeResponse();
    }


    private function makeByOneResponse()
    {
        $results = DB::select( DB::raw(
            "Select
                fencer_id as id,
                last_name,
                first_name,
                by_one_wins,
                by_one_losses,
                (by_one_wins + by_one_losses) as total_by_one,
                (by_one_wins / (by_one_wins + by_one_losses)) as by_one_win_percent,
                (left_total_bouts + right_total_bouts) as total_bouts,
                (by_one_wins + by_one_losses) / (left_total_bouts + right_total_bouts) as by_one_percent
            From
            (
                Select fencer_id, last_name, first_name, by_one_wins, by_one_losses, left_total_bouts, right_total_bouts from
                (
                    SELECT fencer_id, COALESCE(left_bouts, 0) as left_total_bouts, COALESCE(right_bouts, 0) as right_total_bouts, by_one_wins, by_one_losses FROM
                    (
						SELECT
							fencers.id as fencer_id,
							count(fencer_bouts.fencer_id) as by_one_wins
						FROM
							october_business.ajslim_fencingactions_fencers as fencers
						LEFT JOIN
							(
								(SELECT id, left_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where left_score - right_score = 1)
								union
								(SELECT id, right_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where right_score - left_score = 1)
							) as fencer_bouts
						ON fencers.id = fencer_bouts.fencer_id
						GROUP BY fencers.id
					) as by_one_wins_select_total

                    join

                    (
						SELECT
							fencers.id as loss_fencer_id,
							count(fencer_bouts.fencer_id) as by_one_losses
						FROM
							october_business.ajslim_fencingactions_fencers as fencers
						LEFT JOIN
							(
								(SELECT id, right_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where left_score - right_score = 1)
								union
								(SELECT id, left_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where right_score - left_score = 1)
							) as fencer_bouts
						ON fencers.id = fencer_bouts.fencer_id
						GROUP BY fencers.id
					) as by_one_losses_select_total

                    on fencer_id = loss_fencer_id

                    left outer join
                    (SELECT left_fencer_id, count(left_fencer_id) as left_bouts FROM october_business.ajslim_fencingactions_bouts group by left_fencer_id) as L
                    on fencer_id = left_fencer_id

                    left outer join
                    (SELECT right_fencer_id, count(right_fencer_id) as right_bouts FROM october_business.ajslim_fencingactions_bouts group by right_fencer_id) as R
                    on fencer_id = right_fencer_id
                ) as X
                join
                    october_business.ajslim_fencingactions_fencers
                on ajslim_fencingactions_fencers.id = fencer_id
            ) as Y
            order by by_one_win_percent desc"
        ));


        $response = [
            'columns' => [
                'id' => 'id',
                'last_name' => 'string',
                'first_name' => 'string',
                'by_one_wins' => 'integer',
                'by_one_losses' => 'integer',
                'total_by_one' => 'integer',
                'by_one_win_percent' => 'percent',
                'total_bouts' => 'integer',
                'by_one_percent' => 'percent',
            ],
            'data' => $results,
        ];
        return json_encode($response);
    }

    private function makeByOneBoutsResponse($fencerId)
    {

        $results = Bout::whereRaw(
                "(right_fencer_id = ? or left_fencer_id = ?) and abs(left_score - right_score) = 1;",
                [$fencerId, $fencerId]
            )->get();

        $response = $this->makeDataTablesBoutResponse($results, 'bouts');

        return $response;
    }


    private function makeByOneDeResponse()
    {
        $results = DB::select( DB::raw(
            "Select
	            fencer_id as id,
                last_name,
                first_name,
                by_one_wins,
                by_one_losses,
                (by_one_wins + by_one_losses) as total_by_one,
                (by_one_wins / (by_one_wins + by_one_losses)) as by_one_win_percent,
                (left_total_bouts + right_total_bouts) as total_bouts,
                (by_one_wins + by_one_losses) / (left_total_bouts + right_total_bouts) as by_one_percent
            From
            (
                Select fencer_id, last_name, first_name, by_one_wins, by_one_losses, left_total_bouts, right_total_bouts from
                (
                    SELECT fencer_id, COALESCE(left_bouts, 0) as left_total_bouts, COALESCE(right_bouts, 0) as right_total_bouts, by_one_wins, by_one_losses FROM
                    (
						SELECT
							fencers.id as fencer_id,
							count(fencer_bouts.fencer_id) as by_one_wins
						FROM
							october_business.ajslim_fencingactions_fencers as fencers
						LEFT JOIN
							(
								(SELECT id, left_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where left_score - right_score = 1 and (left_score > 5 or right_score > 5))
								union
								(SELECT id, right_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where right_score - left_score = 1 and (left_score > 5 or right_score > 5))
							) as fencer_bouts
						ON fencers.id = fencer_bouts.fencer_id
						GROUP BY fencers.id
					) as by_one_wins_select_total

                    join

                    (
						SELECT
							fencers.id as loss_fencer_id,
							count(fencer_bouts.fencer_id) as by_one_losses
						FROM
							october_business.ajslim_fencingactions_fencers as fencers
						LEFT JOIN
							(
								(SELECT id, right_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where left_score - right_score = 1 and (left_score > 5 or right_score > 5))
								union
								(SELECT id, left_fencer_id as fencer_id FROM october_business.ajslim_fencingactions_bouts where right_score - left_score = 1 and (left_score > 5 or right_score > 5))
							) as fencer_bouts
						ON fencers.id = fencer_bouts.fencer_id
						GROUP BY fencers.id
					) as by_one_losses_select_total

                    on fencer_id = loss_fencer_id

                    left outer join
                    (SELECT left_fencer_id, count(left_fencer_id) as left_bouts FROM october_business.ajslim_fencingactions_bouts where (left_score > 5 or right_score > 5) group by left_fencer_id) as L
                    on fencer_id = left_fencer_id

                    left outer join
                    (SELECT right_fencer_id, count(right_fencer_id) as right_bouts FROM october_business.ajslim_fencingactions_bouts where (left_score > 5 or right_score > 5) group by right_fencer_id) as R
                    on fencer_id = right_fencer_id
                ) as X
                join
                    october_business.ajslim_fencingactions_fencers
                on ajslim_fencingactions_fencers.id = fencer_id
            ) as Y
            order by by_one_win_percent desc"
        ));


        $response = [
            'columns' => [
                'id' => 'id',
                'last_name' => 'string',
                'first_name' => 'string',
                'by_one_wins' => 'integer',
                'by_one_losses' => 'integer',
                'total_by_one' => 'integer',
                'by_one_win_percent' => 'percent',
                'total_bouts' => 'integer',
                'by_one_percent' => 'percent',
            ],
            'data' => $results,
        ];
        return json_encode($response);
    }


    private function makeByOneDeBoutsResponse($fencerId)
    {

        $results = Bout::whereRaw(
            "(right_fencer_id = ? or left_fencer_id = ?) and abs(left_score - right_score) = 1 and (left_score > 5 or right_score > 5);",
            [$fencerId, $fencerId]
        )->get();

        $response = $this->makeDataTablesBoutResponse($results, 'bouts');

        return $response;
    }

}
