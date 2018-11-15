<?php
/**
 * API.php
 * The api json frontend controller
 */

namespace Ajslim\FencingActions\Api;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Ajslim\Fencingactions\Models\Tournament;
use Backend\Classes\Controller;
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class Tournaments extends Controller
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index(
        $tournamentId = null,
        $bouts = null,
        $boutId = null,
        $actions = null,
        $actionId = null
    ) {
        if ($actionId !== null) {
            return $this->displayModel(Action::find($actionId));
        }

        if ($actions === 'actions' && $boutId !== null) {
            return $this->makeDataTablesActionResponse(Bout::find($boutId)->actions);
        }

        if ($boutId !== null) {
            return $this->displayModel(Bout::find($boutId), ['actions']);
        }

        if ($bouts === 'bouts' && $tournamentId !== null) {
            return $this->makeDataTablesResponse(Tournament::find($tournamentId)->bouts);
        }

        if ($tournamentId !== null) {
            return $this->displayModel(Tournament::find($tournamentId), ['bouts']);
        }

        return $this->makeDataTablesResponse(Tournament::all());
    }


    private function displayModel($model, $children = null)
    {
        $array = $model->toArray();
        if ($children !== null) {
            $array['children'] = $children;
        }
        return json_encode($array);
    }

    private function makeDataTablesResponse($collection)
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

    private function makeDataTablesActionResponse($collection)
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
                'top_vote' => $action->topVote(),
                'consensus' => $action->getConsensusAttribute(),
                'difficulty' => $action->getAverageDifficultyRatingAttribute(),
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
