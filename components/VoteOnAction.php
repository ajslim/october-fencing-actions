<?php namespace Ajslim\Fencingactions\Components;

use Ajslim\FencingActions\Models\Action;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\Fencingactions\Models\VoteComment;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\Input;


class VoteOnAction extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'VoteOnAction Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function onRun()
    {

        $post = Input::post();
        if (isset($post['action-id']) === true
        ) {
            $actionId = $post['action-id'];
            $action = Action::find($actionId);

            $vote = Vote::create(
                [
                    'action_id' => $actionId
                ]
            );

            if (isset($post['priority']) === true && isset($post['call']) === true) {
                $vote->call_id = $post['call'];
                $vote->priority = $post['priority'];
            }
            if (isset($post['difficulty']) === true) {
                $vote->difficulty = $post['difficulty'];
            }
            if (isset($post['vote-comment']) === true) {
                $vote->vote_comment_id = $post['vote-comment'];
            }
            $vote->save();



            $attackCount = Vote::where('action_id', $actionId)
                ->where('call_id', 1)
                ->count();

            $counterAttackCount = Vote::where('action_id', $actionId)
                ->where('call_id', 2)
                ->count();

            $riposteCount = Vote::where('action_id', $actionId)
                ->where('call_id', 3)
                ->count();

            $remiseCount = Vote::where('action_id', $actionId)
                ->where('call_id', 4)
                ->count();

            $lineCount = Vote::where('action_id', $actionId)
                ->where('call_id', 5)
                ->count();

            $otherCount = Vote::where('action_id', $actionId)
                ->where('call_id', 6)
                ->count();

            $totalCount = Vote::where('action_id', $actionId)->count();

            $votes = [
                'attack' => $attackCount,
                'counter-attack' => $counterAttackCount,
                'riposte' => $riposteCount,
                'remise' => $remiseCount,
                'line' => $lineCount,
                'other' => $otherCount,
            ];


            $this->page['votes'] = $votes;
            $this->page['totalVotes'] = $totalCount;
            $this->page['voteForm'] = false;
        } else {
            $totalNumberOfActions = Action::count();
            $actionId = rand(1, $totalNumberOfActions);
            $action = Action::find($actionId);
            $this->page['voteForm'] = true;
        }




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
