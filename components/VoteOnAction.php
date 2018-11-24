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
use Illuminate\Support\Facades\Session;

class VoteOnAction extends ComponentBase
{
    /** @var User $user */
    private $user = null;

    /** @var Action $action */
    private $action = null;

    public function componentDetails()
    {
        return [
            'name'        => 'VoteOnAction Component',
            'description' => 'No description provided yet...'
        ];
    }


    private function getVoteArrayForAction()
    {
        $action = $this->action;

        $voteArray = [];

        $leftRightNeitherNames = [
            Action::LEFT_FENCER_ID => 'left',
            Action::RIGHT_FENCER_ID => 'right',
            Action::NEITHER_FENCER_ID => 'neither'
        ];

        $callsArray = $action->getCachedCallsArray();
        foreach ($callsArray as $leftRightNeitherId => $leftRightNeither) {

            $voteArray[$leftRightNeitherNames[$leftRightNeitherId]] = [];
            foreach ($leftRightNeither as $callId => $callCount) {
                $call = Call::find($callId);

                if ($call !== null) {
                    $voteArray[$leftRightNeitherNames[$leftRightNeitherId]][$call->name] = $callCount;
                }
            }
        }
        $voteArray['simultaneous'] = $callsArray[Action::NEITHER_FENCER_ID][Call::SIMULTANEOUS_ID];

        $voteArray['cardLeft'] = $callsArray[Action::LEFT_FENCER_ID][Call::CARD_ID];
        $voteArray['cardRight'] = $callsArray[Action::RIGHT_FENCER_ID][Call::CARD_ID];

        $voteArray['totalPriority'] = $action->votes()->whereNotNull('priority')->count();
        $voteArray['totalCards'] = $action->votes()->whereNotNull('card_for')->count();
        $voteArray['total'] = $action->getCallVotesAttribute()->count();

        return $voteArray;
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


    private function getNewOrExistingVote()
    {
        $actionId = $this->action->id;

        if ($this->user !== null) {
            $vote = Vote::firstOrNew(
                [
                    'user_id' => $this->user->id,
                    'action_id' => $actionId,
                ]
            );

            // reset the vote
            $vote->card_for = null;
            $vote->call_id = null;
            $vote->priority = null;

            if (
                $this->user->hasPermission(['ajslim.fencingactions.fie'])
                && $this->user->id !== 1
            ) {
                $vote->referee_level = 'fie';
            }
        } else {
            $vote = Vote::create(
                [
                    'action_id' => $actionId
                ]
            );
        }

        return $vote;
    }


    private function updateSessionVoteCount()
    {
        $action = $this->action;

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
    }


    private function saveVote()
    {
        $post = Input::post();

        $this->updateSessionVoteCount();
        $vote = $this->getNewOrExistingVote();

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
        $this->action->refresh();
    }


    private function getAction()
    {
        $get = Input::get();

        /* @var Action $action */
        if (isset($get['id'])) {
            $actionId = $get['id'];
            $action = Action::find($actionId);
        } else if (isset($get['action-id'])) {
            $actionId = $get['action-id'];
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

        $this->action = $action;
    }


    private function addVotesToPage()
    {
        $votes = $this->getVoteArrayForAction();

        $this->page['votes'] = $votes;

    }


    private function addBoutAndActionDetailsToPage()
    {
        $action = $this->action;

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


    private function showResults()
    {
        $this->addMotivationalMessage();
        $this->addVotesToPage();
        $this->page['voteForm'] = false;
        $this->page['results'] = true;
        $verifiedCall = $this->action->getVerifiedCallAttribute();
        if ($verifiedCall !== false) {
            $this->page['verified'] = $verifiedCall->call_id;
        }
    }


    private function showVotePage()
    {
        $this->page['voteForm'] = true;

        if ($this->user
            && count($this->action->votes()->where('user_id', $this->user->id)->get()) > 0
        ) {
            $this->page['message'] = "You've refereed this action already";
        }
    }


    private function addGenericPageVariables()
    {
        $get = Input::get();

        if ($this->user) {
            if ($this->user->hasPermission(['ajslim.fencingactions.fie'])
                && $this->user->id !== 1
            ) {
                $this->page['fie'] = true;
            }
        }

        if (isset($get['minvotes'])) {
            $this->page['minvotes'] = $get['minvotes'];
        }

        $voteComments = VoteComment::all();
        $voteCommentsArray = [];
        foreach ($voteComments as $voteComment) {
            $voteCommentsArray[$voteComment->id] = $voteComment->name;
        }

        $this->page['voteComments'] = $voteCommentsArray;

        $this->page['trophy'] = Session::get('newActionTrophy');
        $this->addBoutAndActionDetailsToPage();
    }


    public function onRun()
    {
        $this->user = BackendAuth::getUser();
        $this->getAction();

        $post = Input::post();
        $get = Input::get();

        // If vote form was submitted
        if (isset($post['submit-vote']) === true) {
            $this->saveVote();
            $this->showResults();
        } else {
            // If results page
            if (isset($get['results'])) {
                $this->showResults();
            } else {
                $this->showVotePage();
            }
        }

        $this->addGenericPageVariables();
    }


    public function defineProperties()
    {
        return [];
    }
}
