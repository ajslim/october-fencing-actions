<?php namespace Ajslim\Fencingactions\Components;

use Ajslim\FencingActions\Models\Action;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\Fencingactions\Models\VoteComment;
use Cms\Classes\ComponentBase;
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


    private static function getVotesForAction($actionId)
    {
        $votes = [];
        $attackCount = Vote::where('action_id', $actionId)
            ->where('call_id', 1)
            ->where('priority', 1)
            ->whereNull('card_for')
            ->count();

        $counterAttackCount = Vote::where('action_id', $actionId)
            ->where('call_id', 2)
            ->where('priority', 1)
            ->whereNull('card_for')
            ->count();

        $riposteCount = Vote::where('action_id', $actionId)
            ->where('call_id', 3)
            ->where('priority', 1)
            ->whereNull('card_for')
            ->count();

        $remiseCount = Vote::where('action_id', $actionId)
            ->where('call_id', 4)
            ->where('priority', 1)
            ->whereNull('card_for')
            ->count();

        $lineCount = Vote::where('action_id', $actionId)
            ->where('call_id', 5)
            ->where('priority', 1)
            ->whereNull('card_for')
            ->count();

        $otherCount = Vote::where('action_id', $actionId)
            ->where('call_id', 6)
            ->where('priority', 1)
            ->whereNull('card_for')
            ->count();

        $votes['left'] = [
            'attack' => $attackCount,
            'counter-attack' => $counterAttackCount,
            'riposte' => $riposteCount,
            'remise' => $remiseCount,
            'line' => $lineCount,
            'other' => $otherCount,
        ];


        $attackCount = Vote::where('action_id', $actionId)
            ->where('call_id', 1)
            ->where('priority', 2)
            ->whereNull('card_for')
            ->count();

        $counterAttackCount = Vote::where('action_id', $actionId)
            ->where('call_id', 2)
            ->where('priority', 2)
            ->whereNull('card_for')
            ->count();

        $riposteCount = Vote::where('action_id', $actionId)
            ->where('call_id', 3)
            ->where('priority', 2)
            ->whereNull('card_for')
            ->count();

        $remiseCount = Vote::where('action_id', $actionId)
            ->where('call_id', 4)
            ->where('priority', 2)
            ->whereNull('card_for')
            ->count();

        $lineCount = Vote::where('action_id', $actionId)
            ->where('call_id', 5)
            ->where('priority', 2)
            ->whereNull('card_for')
            ->count();

        $otherCount = Vote::where('action_id', $actionId)
            ->where('call_id', 6)
            ->where('priority', 2)
            ->whereNull('card_for')
            ->count();

        $votes['right'] = [
            'attack' => $attackCount,
            'counter-attack' => $counterAttackCount,
            'riposte' => $riposteCount,
            'remise' => $remiseCount,
            'line' => $lineCount,
            'other' => $otherCount,
        ];

        $cardLeft = Vote::where('action_id', $actionId)
            ->where('card_for', 1)
            ->count();

        $votes['cardLeft'] = $cardLeft;

        $cardRight = Vote::where('action_id', $actionId)
            ->where('card_for', 2)
            ->count();

        $votes['cardRight'] = $cardRight;

        $totalPriority = Vote::where('action_id', $actionId)
            ->whereNotNull('priority')
            ->count();

        $votes['totalPriority'] = $totalPriority;

        $totalCards = Vote::where('action_id', $actionId)
            ->whereNotNull('card_for')
            ->count();

        $votes['totalCards'] = $totalCards;

        $total = Vote::where('action_id', $actionId)
            ->count();
        $votes['total'] = $total;

        return $votes;
    }

    public function onRun()
    {
        $post = Input::post();
        $get = Input::get();
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
            } else {
                $totalNumberOfActions = Action::count();
                $actionId = rand(1, $totalNumberOfActions);
            }

            $action = Action::find($actionId);

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
        $this->page['boutName'] = $action->bout->cache_name;
        $this->page['leftFencer'] = $action->getLeftnameAttribute();
        $this->page['rightFencer'] = $action->getRightnameAttribute();
    }


    public function defineProperties()
    {
        return [];
    }
}
