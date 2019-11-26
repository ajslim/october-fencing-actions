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
class LeftHand extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index()
    {
        return $this->makeLeftHandResponse();
    }

    /**
     * The index controller
     *
     * @return array
     */
    public function indexDe()
    {
        return $this->makeLeftHandDeResponse();
    }


    private function makeLeftHandResponse()
    {
        $results = DB::select( DB::raw(
            "SELECT 
                fencer_id as id,
                last_name,
                first_name,
                left_hand_bouts_won, 
                total_left_hand_bouts, 
                bouts_won, 
                total_bouts,
                (left_hand_bouts_won / total_left_hand_bouts) as left_hand_bout_percentage,
                (bouts_won / total_bouts) as bout_percentage,
                (left_hand_bouts_won / total_left_hand_bouts)-(bouts_won / total_bouts) as left_hand_bout_indicator,
                concat('browse?u=fencers/', fencer_id, '/bouts') as link
            FROM
            (
                SELECT 
                    winning_fencer_id,
                    count(winning_fencer_id) as bouts_won
                FROM
                (
                    SELECT 
                        winning_fencer_id,
                        winning_fencer.hand as winning_hand,
                        losing_fencer_id,
                        losing_fencer.hand as losing_hand
                    FROM
                    (	
                        (SELECT id, left_fencer_id as winning_fencer_id, left_score as winning_score, right_fencer_id as losing_fencer_id, right_score as losing_score FROM october_business.ajslim_fencingactions_bouts where left_score > right_score)
                        union
                        (SELECT id, right_fencer_id as winning_fencer_id, right_score as winning_score, left_fencer_id as losing_fencer_id, left_score as losing_score FROM october_business.ajslim_fencingactions_bouts where right_score > left_score)
                    ) as A
            
                    join
                        october_business.ajslim_fencingactions_fencers as losing_fencer
                    on losing_fencer.id = losing_fencer_id
            
                    join
                        october_business.ajslim_fencingactions_fencers as winning_fencer
                    on winning_fencer.id = winning_fencer_id
                ) AS B
                GROUP BY winning_fencer_id
            ) AS D
            
            join
            (
                SELECT 
                    winning_fencer_id,
                    count(winning_fencer_id) as left_hand_bouts_won
                FROM
                (
                    SELECT 
                        winning_fencer_id,
                        winning_fencer.hand as winning_hand,
                        losing_fencer_id,
                        losing_fencer.hand as losing_hand
                    FROM
                    (	
                        (SELECT id, left_fencer_id as winning_fencer_id, left_score as winning_score, right_fencer_id as losing_fencer_id, right_score as losing_score FROM october_business.ajslim_fencingactions_bouts where left_score > right_score)
                        union
                        (SELECT id, right_fencer_id as winning_fencer_id, right_score as winning_score, left_fencer_id as losing_fencer_id, left_score as losing_score FROM october_business.ajslim_fencingactions_bouts where right_score > left_score)
                    ) as A
            
                    join
                        october_business.ajslim_fencingactions_fencers as losing_fencer
                    on losing_fencer.id = losing_fencer_id
            
                    join
                        october_business.ajslim_fencingactions_fencers as winning_fencer
                    on winning_fencer.id = winning_fencer_id
                ) AS B
                where losing_hand = 'L'
                GROUP BY winning_fencer_id
            ) AS E
            on E.winning_fencer_id = D.winning_fencer_id
            
            join
            (
                SELECT 
                    fencer_id,
                    left_bouts + right_bouts as total_bouts
                FROM
                
                (SELECT left_fencer_id as fencer_id, count(left_fencer_id) as left_bouts FROM october_business.ajslim_fencingactions_bouts group by left_fencer_id) as L
                left join
                (SELECT right_fencer_id, count(right_fencer_id) as right_bouts FROM october_business.ajslim_fencingactions_bouts group by right_fencer_id) as R
                on fencer_id = right_fencer_id
            ) AS C
            on E.winning_fencer_id = C.fencer_id
                
            join
            (
                SELECT 
                    L.fencer_id as total_left_hand_fencer_id,
                    IF(left_bouts IS NULL, 0, left_bouts) + IF(right_bouts IS NULL, 0, right_bouts) as total_left_hand_bouts
                FROM
                
                (
                    SELECT left_fencer_id as fencer_id, count(left_fencer_id) as left_bouts FROM october_business.ajslim_fencingactions_bouts 
                    join october_business.ajslim_fencingactions_fencers on ajslim_fencingactions_fencers.id = right_fencer_id
                    where ajslim_fencingactions_fencers.hand = 'L'
                    group by left_fencer_id
                ) as L
                left join
                (
                    SELECT right_fencer_id as fencer_id, count(right_fencer_id) as right_bouts FROM october_business.ajslim_fencingactions_bouts 
                    join october_business.ajslim_fencingactions_fencers on ajslim_fencingactions_fencers.id = left_fencer_id
                    where ajslim_fencingactions_fencers.hand = 'L'
                    group by right_fencer_id
                ) as R
                on L.fencer_id = R.fencer_id
            ) as F
            on C.fencer_id = total_left_hand_fencer_id
            
            join
                october_business.ajslim_fencingactions_fencers
            on fencer_id = ajslim_fencingactions_fencers.id"
        ));


        $response = [
            'columns' => [
                'id' => 'id',
                'last_name' => 'string',
                'first_name' => 'string',
                'left_hand_bouts_won' => 'integer',
                'total_left_hand_bouts' => 'integer',
                'bouts_won' => 'integer',
                'total_bouts' => 'integer',
                'left_hand_bout_percentage' => 'percent',
                'bout_percentage' => 'percent',
                'left_hand_bout_indicator' => 'percent',
            ],
            'data' => $results,
        ];
        return json_encode($response);
    }


    private function makeLeftHandDeResponse()
    {
        $results = DB::select( DB::raw(
            "SELECT 
                fencer_id as id,
                last_name,
                first_name,
                left_hand_bouts_won, 
                total_left_hand_bouts, 
                bouts_won, 
                total_bouts,
                (left_hand_bouts_won / total_left_hand_bouts) as left_hand_bout_percentage,
                (bouts_won / total_bouts) as bout_percentage,
                (left_hand_bouts_won / total_left_hand_bouts)-(bouts_won / total_bouts) as left_hand_bout_indicator,
                concat('browse?u=fencers/', fencer_id, '/bouts') as link
            FROM
            (
                SELECT 
                    winning_fencer_id,
                    count(winning_fencer_id) as bouts_won
                FROM
                (
                    SELECT 
                        winning_fencer_id,
                        winning_fencer.hand as winning_hand,
                        losing_fencer_id,
                        losing_fencer.hand as losing_hand
                    FROM
                    (	
                        (SELECT id, left_fencer_id as winning_fencer_id, left_score as winning_score, right_fencer_id as losing_fencer_id, right_score as losing_score FROM october_business.ajslim_fencingactions_bouts where left_score > right_score and (left_score > 5 or right_score > 5))
                        union
                        (SELECT id, right_fencer_id as winning_fencer_id, right_score as winning_score, left_fencer_id as losing_fencer_id, left_score as losing_score FROM october_business.ajslim_fencingactions_bouts where right_score > left_score and (left_score > 5 or right_score > 5))
                    ) as A
            
                    join
                        october_business.ajslim_fencingactions_fencers as losing_fencer
                    on losing_fencer.id = losing_fencer_id
            
                    join
                        october_business.ajslim_fencingactions_fencers as winning_fencer
                    on winning_fencer.id = winning_fencer_id
                ) AS B
                GROUP BY winning_fencer_id
            ) AS D
            
            join
            (
                SELECT 
                    winning_fencer_id,
                    count(winning_fencer_id) as left_hand_bouts_won
                FROM
                (
                    SELECT 
                        winning_fencer_id,
                        winning_fencer.hand as winning_hand,
                        losing_fencer_id,
                        losing_fencer.hand as losing_hand
                    FROM
                    (	
                        (SELECT id, left_fencer_id as winning_fencer_id, left_score as winning_score, right_fencer_id as losing_fencer_id, right_score as losing_score FROM october_business.ajslim_fencingactions_bouts where left_score > right_score and (left_score > 5 or right_score > 5))
                        union
                        (SELECT id, right_fencer_id as winning_fencer_id, right_score as winning_score, left_fencer_id as losing_fencer_id, left_score as losing_score FROM october_business.ajslim_fencingactions_bouts where right_score > left_score and (left_score > 5 or right_score > 5))
                    ) as A
            
                    join
                        october_business.ajslim_fencingactions_fencers as losing_fencer
                    on losing_fencer.id = losing_fencer_id
            
                    join
                        october_business.ajslim_fencingactions_fencers as winning_fencer
                    on winning_fencer.id = winning_fencer_id
                ) AS B
                where losing_hand = 'L'
                GROUP BY winning_fencer_id
            ) AS E
            on E.winning_fencer_id = D.winning_fencer_id
            
            join
            (
                SELECT 
                    fencer_id,
                    left_bouts + right_bouts as total_bouts
                FROM
                
                (SELECT left_fencer_id as fencer_id, count(left_fencer_id) as left_bouts FROM october_business.ajslim_fencingactions_bouts where (left_score > 5 or right_score > 5) group by left_fencer_id) as L
                left join
                (SELECT right_fencer_id, count(right_fencer_id) as right_bouts FROM october_business.ajslim_fencingactions_bouts where (left_score > 5 or right_score > 5) group by right_fencer_id) as R
                on fencer_id = right_fencer_id
            ) AS C
            on E.winning_fencer_id = C.fencer_id
                
            join
            (
                SELECT 
                    L.fencer_id as total_left_hand_fencer_id,
                    IF(left_bouts IS NULL, 0, left_bouts) + IF(right_bouts IS NULL, 0, right_bouts) as total_left_hand_bouts
                FROM
                
                (
                    SELECT left_fencer_id as fencer_id, count(left_fencer_id) as left_bouts FROM october_business.ajslim_fencingactions_bouts 
                    join october_business.ajslim_fencingactions_fencers on ajslim_fencingactions_fencers.id = right_fencer_id
                    where (left_score > 5 or right_score > 5) 
                    and ajslim_fencingactions_fencers.hand = 'L'
                    group by left_fencer_id
                ) as L
                left join
                (
                    SELECT right_fencer_id as fencer_id, count(right_fencer_id) as right_bouts FROM october_business.ajslim_fencingactions_bouts 
                    join october_business.ajslim_fencingactions_fencers on ajslim_fencingactions_fencers.id = left_fencer_id
                    where (left_score > 5 or right_score > 5) 
                    and ajslim_fencingactions_fencers.hand = 'L'
                    group by right_fencer_id
                ) as R
                on L.fencer_id = R.fencer_id
            ) as F
            on C.fencer_id = total_left_hand_fencer_id
            
            join
                october_business.ajslim_fencingactions_fencers
            on fencer_id = ajslim_fencingactions_fencers.id"
        ));


        $response = [
            'columns' => [
                'id' => 'id',
                'last_name' => 'string',
                'first_name' => 'string',
                'left_hand_bouts_won' => 'integer',
                'total_left_hand_bouts' => 'integer',
                'bouts_won' => 'integer',
                'total_bouts' => 'integer',
                'left_hand_bout_percentage' => 'percent',
                'bout_percentage' => 'percent',
                'left_hand_bout_indicator' => 'percent',
            ],
            'data' => $results,
        ];
        return json_encode($response);
    }
}
