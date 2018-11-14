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


class VoteOnAction extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'VoteOnAction Component',
            'description' => 'No description provided yet...'
        ];
    }


    private static function getVoteCount($actionId, $callId, $priority)
    {
        return Vote::where('action_id', $actionId)
            ->where('call_id', $callId)
            ->where('priority', $priority)
            ->whereNull('card_for')
            ->count();
    }

    private static function getVotesForAction($actionId)
    {
        $votes = [];
        $attackCount = self::getVoteCount($actionId, Call::ATTACK_ID, Action::LEFT_FENCER_ID);
        $counterAttackCount = self::getVoteCount($actionId, Call::COUNTER_ATTACK_ID, Action::LEFT_FENCER_ID);
        $riposteCount = self::getVoteCount($actionId, Call::RIPOSTE_ID, Action::LEFT_FENCER_ID);
        $remiseCount = self::getVoteCount($actionId, Call::REMISE_ID, Action::LEFT_FENCER_ID);
        $lineCount = self::getVoteCount($actionId, Call::LINE_ID, Action::LEFT_FENCER_ID);
        $otherCount = self::getVoteCount($actionId, Call::OTHER_ID, Action::LEFT_FENCER_ID);

        $votes['left'] = [
            'attack' => $attackCount,
            'counter-attack' => $counterAttackCount,
            'riposte' => $riposteCount,
            'remise' => $remiseCount,
            'line' => $lineCount,
            'other' => $otherCount,
        ];

        $attackCount = self::getVoteCount($actionId, Call::ATTACK_ID, Action::RIGHT_FENCER_ID);
        $counterAttackCount = self::getVoteCount($actionId, Call::COUNTER_ATTACK_ID, Action::RIGHT_FENCER_ID);
        $riposteCount = self::getVoteCount($actionId, Call::RIPOSTE_ID, Action::RIGHT_FENCER_ID);
        $remiseCount = self::getVoteCount($actionId, Call::REMISE_ID, Action::RIGHT_FENCER_ID);
        $lineCount = self::getVoteCount($actionId, Call::LINE_ID, Action::RIGHT_FENCER_ID);
        $otherCount = self::getVoteCount($actionId, Call::OTHER_ID, Action::RIGHT_FENCER_ID);

        $votes['right'] = [
            'attack' => $attackCount,
            'counter-attack' => $counterAttackCount,
            'riposte' => $riposteCount,
            'remise' => $remiseCount,
            'line' => $lineCount,
            'other' => $otherCount,
        ];

        $cardLeft = Vote::where('action_id', $actionId)->where('card_for', Action::LEFT_FENCER_ID)->count();
        $votes['cardLeft'] = $cardLeft;

        $cardRight = Vote::where('action_id', $actionId)->where('card_for', Action::RIGHT_FENCER_ID)->count();
        $votes['cardRight'] = $cardRight;

        $totalPriority = Vote::where('action_id', $actionId)->whereNotNull('priority')->count();
        $votes['totalPriority'] = $totalPriority;

        $totalCards = Vote::where('action_id', $actionId)->whereNotNull('card_for')->count();
        $votes['totalCards'] = $totalCards;

        $total = Vote::where('action_id', $actionId)->where('vote_comment_id', 1)->count();
        $votes['total'] = $total;

        return $votes;
    }


    private static function sqlToActionArray($sql)
    {
        $actionArray = DB::connection('business')->select($sql);
        $idArray = array_map(function($element) { return $element->id; }, $actionArray);
        return Action::find($idArray);
    }

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




    private function getRandomActionFromCollection(Collection $collection)
    {
        $totalNumberOfActions = count($collection);
        $actionIndex = rand(1, $totalNumberOfActions) - 1;
        return $collection->get($actionIndex);
    }

    public function onRun()
    {
        $post = Input::post();
        $get = Input::get();

        if (isset($get['minvotes'])) {
            $this->page['minvotes'] = $get['minvotes'];
        }

        if (isset($post['action-id']) === true) {
            $actionId = $post['action-id'];
            $action = Action::find($actionId);

            $vote = Vote::create(
                [
                    'action_id' => $actionId
                ]
            );

            if (isset($post['priority']) === true
                && isset($post['call']) === true
                && $post['priority'] !== '0'
            ) {
                $vote->call_id = $post['call'];
                $vote->priority = $post['priority'];
            }
            if (isset($post['card-for']) === true && $post['card-for'] !== '0') {
                $vote->card_for = $post['card-for'];
            }

            if (isset($post['difficulty']) === true) {
                $vote->difficulty = $post['difficulty'];
            }
            if (isset($post['vote-comment']) === true) {
                $vote->vote_comment_id = $post['vote-comment'];
            }
            $vote->save();

            return Redirect::to("/?id=$actionId&results=true");

        } else {
            // Unlikely, but the get variable overrides the post
            if (isset($get['id'])) {
                $actionId = $get['id'];
                $action = Action::find($actionId);
            } else if (isset($get['minvotes'])) {
                $action = $this->getRandomActionFromCollection($this->getActionsWithMinimumVotes($get['minvotes']));
            } else {
                $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());
            }

            if ($action === null) {
                $this->page['message'] = "No actions found with that many votes";
                $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());
            }
            $actionId = $action->id;

            if (isset($get['results'])) {
                $this->page['voteForm'] = false;
                $this->page['results'] = true;
            } else {
                $this->page['voteForm'] = true;
            }
        }

        $votes = self::getVotesForAction($actionId);

        $this->page['votes'] = $votes;

        $voteComments = VoteComment::all();
        $voteCommentsArray = [];
        foreach ($voteComments as $voteComment) {
            $voteCommentsArray[$voteComment->id] = $voteComment->name;
        }

        $this->page['voteComments'] = $voteCommentsArray;
        $this->page['actionId'] = $action->id;
        $this->page['videoUrl'] = $action->video_url;

        $this->page['boutName'] = '';
        if ($action->bout !== null
            && $action->bout->tournament !== null) {
            $this->page['boutName'] = $action->bout->tournament->year . ' ' . $action->bout->tournament->place . ' ' . $action->bout->tournament->name;
        }

        $this->page['leftFencer'] = $action->getLeftnameAttribute();
        $this->page['rightFencer'] = $action->getRightnameAttribute();
    }


    public function defineProperties()
    {
        return [];
    }
}
