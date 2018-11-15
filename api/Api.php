<?php
/**
 * API.php
 * The api json frontend controller
 */

namespace Ajslim\FencingActions\Api;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Call;
use Ajslim\Fencingactions\Models\Tournament;
use Backend\Classes\Controller;
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class Api extends Controller
{
    public static function idToAction($id)
    {
        if ($id === Call::ATTACK_ID) {
            return "Attack";
        } else if ($id === Call::COUNTER_ATTACK_ID){
            return "Counter Attack";
        } else if ($id === Call::RIPOSTE_ID){
            return "Riposte";
        } else if ($id === Call::REMISE_ID){
            return "Remise";
        } else if ($id === Call::LINE_ID){
            return "Line";
        } else if ($id === Call::OTHER_ID){
            return "Other";
        }
        return null;
    }


    public function displayModel($model, $children = null)
    {
        $array = $model->toArray();
        if ($children !== null) {
            $array['children'] = $children;
        }
        return json_encode($array);
    }

    public function makeDataTablesResponse($collection)
    {
        $draw = (integer) Input::get('draw');
        $start = Input::get('start');
        $length = Input::get('length');

        $recordsTotal = count($collection);

        if ($start !== null && $length !== null) {
            $records = array_values($collection->slice($start, $length)->all());
        } else {
            $records = $collection->toArray();
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $records,
        ];

        return json_encode($response);
    }

    public function makeDataTablesActionResponse($collection)
    {
        $draw = (integer) Input::get('draw');
        $start = Input::get('start');
        $length = Input::get('length');

        $recordsTotal = count($collection);

        if ($start !== null && $length !== null) {
            $collection = $collection->slice($start, $length);
        }

        $records = [];

        /** @var Action $action */
        foreach ($collection as $action) {
            $records[] = [
                'thumb' => $action->thumb_url,
                'votes' => count($action->votes),
                'top_vote' => self::idToAction($action->topVote()),
                'consensus' => round($action->getConsensusAttribute(), 3),
                'difficulty' => round($action->getAverageDifficultyRatingAttribute(), 3),
                'time' => $action->time,
                'link' => '/?id=' . $action->id . '&results=true'
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'data' => $records,
        ];
        return json_encode($response);
    }
}
