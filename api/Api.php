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
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class Api extends Controller
{
    public function displayModel($model, $children = null)
    {
        $array = $model->toArray();
        if ($children !== null) {
            $array['children'] = $children;
        }
        return json_encode($array);
    }

    public function makeFencerResponse(Fencer $fencer, $children)
    {

        $calls = $fencer->getCallPercentagesAttribute();
        $callsAgainst = $fencer->getCallPercentagesAgainstAttribute();
        $response = [
            'id' => $fencer->id,
            'last_name' => $fencer->last_name,
            'first_name' => $fencer->first_name,
            'gender' => $fencer->gender,
            'fie_site_number' => $fencer->fie_site_number,
            'photo_url' => $fencer->photo_url,
            'country_code' => $fencer->country_code,
            'birth' => $fencer->birth,
            'highest_rank' => $fencer->highest_rank,
            'primary_weapon' => $fencer->primary_weapon,
            'call_percentages' => [
                'for' => $calls,
                'against' => $callsAgainst,
                'average_fencer' => $fencer->getAllFencersAverageActionsCallPercentagesAttribute(),
            ],
            'total_actions_for' => count($fencer->getActionsForAttribute()),
            'total_actions_against' => count($fencer->getActionsAgainstAttribute()),
            'created_at' => $fencer->created_at,
            'updated_at' => $fencer->created_at,
            'children' => $children,
        ];

        return json_encode($response);

    }


    public function makeCollectionColumns($collection)
    {
        $firstRowArray = $collection->first()->toArray();
        $columns = [];
        foreach ($firstRowArray as $columnName => $column) {
            if ($columnName === 'id') {
                $columns['id'] = 'id';
            } else {
                $columns[$columnName] = gettype($column);
            }
        }
        return $columns;
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
            'columns' => $this->makeCollectionColumns($records),
            'data' => $records,
        ];

        return json_encode($response);
    }


    public function makeDataTablesFencerResponse($collection)
    {

        $draw = (integer) Input::get('draw');
        $start = Input::get('start');
        $length = Input::get('length');

        $recordsTotal = count($collection);

        if ($start !== null && $length !== null) {
            $collection = $collection->slice($start, $length);
        }

        $records = [];
        /** @var Fencer $fencer */
        foreach ($collection as $fencer) {
            $records[] = [
                'id' => $fencer->id,
                'last_name' => $fencer->last_name,
                'first_name' => $fencer->first_name,
                'total_actions_for' => count($fencer->getActionsForAttribute()),
                'total_actions_against' => count($fencer->getActionsAgainstAttribute()),
                'country_code' => $fencer->country_code,
                'birth' => $fencer->birth,
                'highest_rank' => $fencer->highest_rank,
                'primary_weapon' => $fencer->primary_weapon,
                'gender' => $fencer->gender,
                'fie_site_number' => $fencer->fie_site_number,
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'columns' => $this->makeCollectionColumns($records),
            'data' => $records,
        ];
        return json_encode($response);
    }


    public function makeActionsColumns()
    {
        return [
            'id' => 'id',
            'thumb' => 'image',
            'votes' => 'integer',
            'top_vote' => 'string',
            'is_verified' => 'boolean',
            'confidence' => 'double',
            'consensus' => 'double',
            'difficulty' => 'double',
            'time' => 'integer',
            'bout_name' => 'string',
            'link' => 'link'
        ];
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
                'id' => $action->id,
                'thumb' => $action->thumb_url,
                'votes' => $action->vote_count_cache,
                'top_vote' => $action->top_vote_name_cache,
                'is_verified' => $action->is_verified_cache,
                'confidence' => round($action->confidence_cache, 3),
                'consensus' => round($action->consensus_cache, 3),
                'difficulty' => round($action->average_difficulty_cache, 3),
                'time' => $action->time,
                'bout_name' => $action->bout->name,
                'link' => '/?id=' . $action->id . '&results=true'
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'columns' => $this->makeActionsColumns(),
            'data' => $records,
        ];
        return json_encode($response);
    }
}
