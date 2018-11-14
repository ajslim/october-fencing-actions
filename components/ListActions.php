<?php namespace Ajslim\Fencingactions\Components;

use Ajslim\FencingActions\Models\Action;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\Fencingactions\Models\Call;
use Ajslim\Fencingactions\Models\VoteComment;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;


class ListActions extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'List actions Component',
            'description' => 'No description provided yet...'
        ];
    }

    private static function sqlToActionArray($sql)
    {
        $actionArray = DB::connection('business')->select($sql);
        $idArray = array_map(function($element) { return $element->id; }, $actionArray);
        return Action::find($idArray);
    }


    /**
     * @param $minimumVotes
     *
     * @return Collection
     */
    private function getActionsWithMinimumVotes($minimumVotes)
    {
        $actionArray = DB::connection('business')->select('
            SELECT ajslim_fencingactions_actions.id, COUNT(ajslim_fencingactions_votes.id) as VoteCount
	          FROM ajslim_fencingactions_actions
		        LEFT OUTER JOIN 
		            (
		              SELECT * FROM ajslim_fencingactions_votes
		              WHERE vote_comment_id = 1
		            ) 
		            AS ajslim_fencingactions_votes
		          ON ajslim_fencingactions_actions.id = ajslim_fencingactions_votes.action_id
		        GROUP BY ajslim_fencingactions_actions.id
		        HAVING VoteCount >= ?
        ', [$minimumVotes]);

        $idArray = [];
        if (count($actionArray) > 0) {
            $idArray = array_map(function ($element) {
                return $element->id;
            }, $actionArray);
        }
        return Action::find($idArray);
    }


    private function getActionsWithNoVotes()
    {
        $query = '
          SELECT id 
            FROM ajslim_fencingactions_actions actions 
            WHERE NOT EXISTS (
              SELECT * 
                FROM ajslim_fencingactions_votes votes 
                WHERE votes.action_id = actions.id
            );
        ';

        return self::sqlToActionArray($query);
    }


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

    public function onRun()
    {

        $actions = $this->getActionsWithMinimumVotes(0);


        $linkArray = [];

        /* @var Action $action */
        foreach ($actions as $action) {
            $linkArray[] = [
                'name' => $action->bout->name,
                'votes' => count($action->votes),
                'call' => self::idToAction($action->topVote()),
                'consensus' => round($action->getConsensusAttribute() * 100) . '%',
                'time' => $action->time,
                'link' => '/?id=' . $action->id . '&results=true'
            ];
        }

        $this->page['actions'] = $linkArray;
    }


    public function defineProperties()
    {
        return [];
    }
}
