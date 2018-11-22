<?php namespace Ajslim\Fencingactions\Components;

use Ajslim\FencingActions\Models\Action;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\Fencingactions\Models\Call;
use Ajslim\Fencingactions\Models\VoteComment;
use Backend\Facades\BackendAuth;
use Backend\Models\User;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;


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

        $votes['simultaneous'] = self::getVoteCount($actionId, Call::SIMULTANEOUS_ID, Action::NEITHER_FENCER_ID);

        $cardLeft = Vote::where('action_id', $actionId)->where('card_for', Action::LEFT_FENCER_ID)->count();
        $votes['cardLeft'] = $cardLeft;

        $cardRight = Vote::where('action_id', $actionId)->where('card_for', Action::RIGHT_FENCER_ID)->count();
        $votes['cardRight'] = $cardRight;

        $totalPriority = Vote::where('action_id', $actionId)->whereNotNull('priority')->count();
        $votes['totalPriority'] = $totalPriority;

        $totalCards = Vote::where('action_id', $actionId)->whereNotNull('card_for')->count();
        $votes['totalCards'] = $totalCards;

        $action = Action::find($actionId);
        $total = count($action->getCallVotesAttribute());
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


    private function saveVote($actionId, $user)
    {
        $post = Input::post();

        $action = Action::find($actionId);
        $votes = $action->getCallVotesAttribute();

        $voteCount = Session::get('voteCount', 0);
        $voteCount += 1;
        Session::put('voteCount', $voteCount);

        if (count($votes) === 0) {
            $newActionVoteCount = Session::get('newActionVoteCount', 0);
            $newActionVoteCount += 1;
            Session::put('newActionVoteCount', $newActionVoteCount);
            Session::put('newAction', true);
        }


        /** @var User $user */
        if ($user) {
            $vote = Vote::updateOrCreate(
                [
                    'action_id' => $actionId,
                    'user_id' => $user->id
                ]
            );

            if ($user->hasPermission(['ajslim.fencingactions.fie'])) {
                $vote->referee_level = 'fie';
            }
        } else {
            $vote = Vote::create(
                [
                    'action_id' => $actionId
                ]
            );
        }

        if (isset($post['priority']) === true
            && isset($post['call']) === true
            && $post['priority'] !== '0'
        ) {
            $vote->call_id = $post['call'];
            $vote->priority = $post['priority'];
        }

        if (isset($post['priority']) === true
            && isset($post['call']) === true
            && $post['priority'] === '0'
            && $post['call'] === '7'
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

        // Log the ip address of the vote, just in case some craziness happens
        $vote->ip_address = $_SERVER['REMOTE_ADDR'];

        $vote->save();
    }


    private function getAction()
    {
        $get = Input::get();

        /* @var Action $action */
        if (isset($get['id'])) {
            $actionId = $get['id'];
            $action = Action::find($actionId);
        } else if (isset($get['minvotes'])) {
            $action = $this->getRandomActionFromCollection($this->getActionsWithMinimumVotes($get['minvotes']));

            // Just retry once, if they get another non action, they can label it
            if ($action->getIsNotActionAttribute() === true) {
                $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());
            }
        } else {
            $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());

            // Just retry once, if they get another non action, they can label it
            if ($action->getIsNotActionAttribute() === true) {
                $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());
            }
        }

        if ($action === null) {
            $this->page['warning'] = "No actions found with that many votes";
            $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());

            // Just retry once, if they get another non action, they can label it
            if ($action->getIsNotActionAttribute() === true) {
                $action = $this->getRandomActionFromCollection($this->getActionsWithNoVotes());
            }
        }

        return $action;
    }


    private function addVotesToPage($actionId)
    {
        $votes = self::getVotesForAction($actionId);

        $this->page['votes'] = $votes;

        $voteComments = VoteComment::all();
        $voteCommentsArray = [];
        foreach ($voteComments as $voteComment) {
            $voteCommentsArray[$voteComment->id] = $voteComment->name;
        }

        $this->page['voteComments'] = $voteCommentsArray;
    }


    private function addBoutAndActionDetailsToPage(Action $action)
    {
        $this->page['actionId'] = $action->id;
        $this->page['videoUrl'] = $action->video_url;

        $this->page['boutName'] = '';
        if ($action->bout !== null
            && $action->bout->tournament !== null) {
            $this->page['boutName'] = $action->bout->tournament->year . ' ' . $action->bout->tournament->place . ' ' . $action->bout->tournament->name;
            $this->page['boutVideoUrl'] = $action->bout->video_url . "&t=" . ($action->time - 8);
        }

        $this->page['leftFencer'] = $action->getLeftnameAttribute();
        $this->page['rightFencer'] = $action->getRightnameAttribute();
    }


    private function addMotivationalMessage()
    {
        $newAction = Session::pull('newAction', false);

        if ($newAction) {
            $newActionVoteCount = Session::get('newActionVoteCount', 0);

            if ($newActionVoteCount < 10) {
                $actionsRemaining = 10 - $newActionVoteCount;
                $message = "You've refereed $newActionVoteCount new actions! Keep it up! Only "
                 . "$actionsRemaining more to go!";
            } else if ($newActionVoteCount == 10) {
                $message = '<i class="fa fa-trophy" style="font-size:48px;color:#B77B4B"></i> You\'ve earned the bronze trophy!';
                Session::put('newActionTrophy', 'bronze');
            } else if ($newActionVoteCount < 25) {
                $actionsRemaining = 25 - $newActionVoteCount;
                $message = "You've refereed $newActionVoteCount new actions! Keep it up! Only "
                    . "$actionsRemaining more to go!";
            } else if ($newActionVoteCount == 25) {
                $message = '<i class="fa fa-trophy" style="font-size:48px;color:#D2CFD5"></i> You\'ve earned the silver trophy!';
                Session::put('newActionTrophy', 'silver');
            } else if ($newActionVoteCount < 50) {
                $actionsRemaining = 50 - $newActionVoteCount;
                $message = "You've refereed $newActionVoteCount new actions! Keep it up! Only "
                    . "$actionsRemaining more to go!";
            } else if ($newActionVoteCount == 50) {
                $message = '<i class="fa fa-trophy" style="font-size:48px;color:#E8DC1D"></i> You\'ve earned the gold trophy!';
                Session::put('newActionTrophy', 'gold');
            } else {
                $message = "You've refereed $newActionVoteCount new actions! Keep it up!";
            }

            $this->page['message'] = $message;
        } else {
            $voteCount = Session::get('voteCount', 0);

            if (in_array($voteCount, [5, 10, 15, 20, 30, 40, 50, 60, 70, 80, 90, 100, 150, 200])) {
                $message = "You've refereed $voteCount actions! Keep it up!";
                $this->page['message'] = $message;
            }
        }
    }


    public function onRun()
    {
        $user = BackendAuth::getUser();

        if ($user) {
            if ($user->hasPermission(['ajslim.fencingactions.fie'])) {
                $this->page['fie'] = true;
            }
        }

        $post = Input::post();
        $get = Input::get();

        if (isset($get['minvotes'])) {
            $this->page['minvotes'] = $get['minvotes'];
        }

        // If vote form was submitted
        if (isset($post['action-id']) === true) {
            $actionId = $post['action-id'];
            $this->saveVote($actionId, $user);
            return Redirect::to("/?id=$actionId&results=true");
        } else {
            $action = $this->getAction();
            $actionId = $action->id;

            if (isset($get['results'])) {
                $this->addMotivationalMessage();
                $this->page['voteForm'] = false;
                $this->page['results'] = true;
            } else {
                $this->page['voteForm'] = true;
            }
        }

        $this->page['trophy'] = Session::get('newActionTrophy');
        $this->addVotesToPage($actionId);
        $this->addBoutAndActionDetailsToPage($action);
    }


    public function defineProperties()
    {
        return [];
    }
}
