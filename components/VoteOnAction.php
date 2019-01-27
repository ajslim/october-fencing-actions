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
    private $isFieUser = false;
    private $referee_level = null;

    /** @var Action $action */
    private $action = null;

    /** @var Vote $vote */
    private $vote = null;

    private $messages = [];
    private $warnings = [];
    private $infoMessages = [];

    private $isNewAction = false;

    private $maxFieVoteCount = 20;
    private $verifierThreshold = 10;
    private $beginnerThreshold = 2;
    private $easyWrongPunishment = 10;
    private $mediumWrongPunishment = 2;

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


    private function getActions()
    {
        return Action::whereDoesntHave('votes', function ($query) {
            $query->where('vote_comment_id', 2);
        })->get();
    }


    private function getDifficultActions()
    {
        return Action::all()->filter(function ($action) {
            /** @var Action $action */
            return (count($action->votes) > 3 && $action->getConfidenceAttribute() < 0.5);
        });
    }

    private function getActionsWithNoVotes()
    {
        return Action::doesnthave('votes')->get();
    }

    private function getVerifiedActions()
    {
        return Action::where('is_verified_cache', '1')->get();
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
            $this->isNewAction = true;
        }
    }


    private function saveVote()
    {
        $post = Input::post();

        $this->updateSessionVoteCount();
        $vote = $this->getNewOrExistingVote();

        if (isset($post['priority']) === true
            && isset($post['call']) === true
            && (
                $post['priority'] !== '0'
                || $post['call'] === '7'
            )

        ) {
            $vote->call_id = intval($post['call']);
            $vote->priority = intval($post['priority']);
        }

        if (isset($post['card-for']) === true && $post['card-for'] !== '0') {
            $vote->card_for = intval($post['card-for']);
        }

        if (isset($post['difficulty']) === true) {
            $vote->difficulty = intval($post['difficulty']);
        }
        if (isset($post['vote-comment']) === true) {
            $vote->vote_comment_id = intval($post['vote-comment']);
        }

        // Log the ip address of the vote, just in case some craziness happens
        $vote->ip_address = $_SERVER['REMOTE_ADDR'];
        $vote->referee_level = $this->referee_level;

        $vote->save();

        $this->vote = $vote;

        // ensures clean models
        $this->vote->refresh();
        $this->action->updateCacheColumns();
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
        } else if (isset($get['onlyverified'])) {
            $action = $this->getVerifiedActions()->random();
            $this->page['onlyverified'] = true;
        } else if (isset($get['difficult'])) {
            $action = $this->getDifficultActions()->random();
            $this->page['difficult'] = true;
        } else if (isset($get['fresh'])) {
            $action = $this->getActionsWithNoVotes()->random();
            $this->page['fresh'] = true;
        } else if ($this->isFieUser === true) {
            $random = rand(1, 5);
            if ($random < 3) {
                $action = $this->getActionsWithNoVotes()->random();
            } else if ($random < 5) {
                $action = $this->getDifficultActions()->random();
            } else {
                $action = $this->getActions()->random();
            }
        } else {
            $fieVoteCount = Session::get('fieVoteCount', 0);

            // Voters must get 2 verified actions right to get non-verified actions
            // Voters who are not verified will have 2/3 of their actions verified
            // 'verifier' voters will get 1/4 of their actions as verified
            // 'verifier' users will get 1/4 of their actions as fresh too
            if ($fieVoteCount <= $this->beginnerThreshold) {
                $action = $this->getVerifiedActions()->random();
            } else if ($fieVoteCount <  $this->maxFieVoteCount) {
                $random = rand(1, 3);
                if ($random === 1) {
                    $action = $this->getVerifiedActions()->random();
                } else {
                    $action = $this->getActions()->random();
                }
            } else {
                $random = rand(1, 5);
                if ($random === 1) {
                    $action = $this->getVerifiedActions()->random();
                } else if ($random === 2) {
                    $action = $this->getActionsWithNoVotes()->random();
                } else {
                    $action = $this->getActions()->random();
                }
            }
        }
        if ($action === null) {
            $this->warnings[] = "No actions found";

            $action = $this->getActions()->random();
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


    private function addToFieVoteCount($count)
    {
        $fieVoteCount = Session::get('fieVoteCount', 0);
        $fieVoteCount += $count;

        if ($fieVoteCount < 0) {
            Session::put('fieVoteCount', 0);
        } else if ($fieVoteCount > $this->maxFieVoteCount) {
            Session::put('fieVoteCount', $this->maxFieVoteCount);
        } else {
            Session::put('fieVoteCount', $fieVoteCount);
        }

    }


    private function checkCorrect()
    {
        if ($this->isFieUser) {
            // Fie users are correct by definition
            return;
        }

        $verifiedVote = $this->action->getVerifiedVoteAttribute();

        if ($verifiedVote !== false) {

            if ($verifiedVote->isSameCall($this->vote)) {
                $correctVerifiedVoteCount = Session::get('correctVerifiedVoteCount', 0);
                Session::put('correctVerifiedVoteCount', $correctVerifiedVoteCount + 1);

                $this->messages[] = 'Correct! This action has been verified and you got it right!';
            } else {
                $this->warnings[] = 'Incorrect! This action has been verified and the correct call was: ' . $verifiedVote->toString();
            }

            $fieConsensus = $this->action->getFieConsensusVoteAttribute();
            $fieDifficultyFloor = $this->action->getFieDifficultyFloorAttribute();
            if ($fieConsensus !== false && $fieDifficultyFloor !== false) {
                if ($verifiedVote->isSameCall($this->vote)) {
                    $this->addToFieVoteCount(1);
                } else {
                    // If they get an easy one wrong, knock off 10
                    if ($fieDifficultyFloor === 1) {
                        $this->addToFieVoteCount(-1 * $this->easyWrongPunishment);
                    }

                    // If they get a medium one wrong, knock them back 3
                    if ($fieDifficultyFloor === 2) {
                        $this->addToFieVoteCount(-1 * $this->mediumWrongPunishment);
                    }
                }
            }
        } else {
            $this->infoMessages[] = 'This action has not been verified yet';
        }
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

            $this->messages[] = $message;
        } else {
            $voteCount = Session::get('voteCount', 0);

            if (in_array($voteCount, [5, 10, 15, 20, 30, 40, 50, 60, 70, 80, 90, 100, 150, 200])) {
                $message = "You've refereed $voteCount actions! Keep it up!";
                $this->messages[] = $message;
            }
        }
    }


    private function showResults()
    {
        $this->action->updateCacheColumns();

        if ($this->vote !== null) {
            $this->checkCorrect();
            $this->addMotivationalMessage();
        }
        $this->addVotesToPage();
        $this->page['voteForm'] = false;
        $this->page['results'] = true;
        $verifiedCall = $this->action->getVerifiedVoteAttribute();
        if ($verifiedCall !== false) {
            $this->page['verified'] = $verifiedCall->call_id;
            $this->page['verifiedString'] = $verifiedCall->toString();
        }
    }


    private function showVotePage()
    {
        $this->page['voteForm'] = true;

        if ($this->user
            && count($this->action->votes()->where('user_id', $this->user->id)->get()) > 0
        ) {
            $this->infoMessages[] = "You've refereed this action already";
        }
    }


    private function getUserDetails()
    {
        $this->user = BackendAuth::getUser();

        if (Session::get('fieVoteCount', 0) >= $this->verifierThreshold) {
            $this->referee_level = 'verifier';
            $this->page['verifier'] = true;
        }

        if ($this->user) {
            if ($this->user->hasPermission(['ajslim.fencingactions.fie'])
                && $this->user->id !== 1
            ) {
                $this->isFieUser = true;
                $this->page['fie'] = true;
                $this->referee_level = 'fie';
            }
        }
    }

    private function addGenericPageVariables()
    {
        $get = Input::get();

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
        $this->page['messages'] = $this->messages;
        $this->page['warnings'] = $this->warnings;
        $this->page['infoMessages'] = $this->infoMessages;
    }


    public function onRun()
    {
        $this->getUserDetails();
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
