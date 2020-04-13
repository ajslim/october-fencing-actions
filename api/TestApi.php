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
use Ajslim\Fencingactions\Models\Test;
use Ajslim\Fencingactions\Models\Tournament;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\FencingActions\Repositories\ActionRepository;
use Backend\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Request;

/**
 * Api Controller
 */
class TestApi extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index() {
        $easyVerifiedActions = ActionRepository::getEasyVerifiedActions()->paginate(3);
        $mediumVerifiedActions = ActionRepository::getMediumVerifiedActions()->paginate(3);
        $difficultVerifiedActions = ActionRepository::getDifficultVerifiedActions()->paginate(2);
        $newActions = ActionRepository::getActionsWithNoVotes()->paginate(2);

        /** @var Action[] $actions */
        $actions = $easyVerifiedActions->merge($mediumVerifiedActions);
        $actions = $actions->merge($difficultVerifiedActions);
        $actions = $actions->merge($newActions);

        foreach($actions as $action) {
            $action->updateCacheColumns();
        }

        $questions = [];
        $ids = [];
        foreach($actions as $action) {
            $ids[] = $action->id;

            $questions[] = [
                'video_url' => $action->getVideoAttribute(),
                'left_fencer_name' => $action->getLeftnameAttribute(),
                'right_fencer_name' => $action->getRightnameAttribute(),
                'tournament' => $action->getRightnameAttribute(),
                'thumb_url' => $action->thumb_url,
            ];
        }

        $test = Test::create([
            'action_ids' => implode(',', $ids)
        ]);

        return response(
            json_encode([
                'testid' => $test->id,
                'actions' => $questions
            ])
        )->withHeaders([
            'Content-Type' => 'text/json; charset=utf-8'
        ]);
    }


    /**
     * The index controller
     *
     * @return array
     */
    public function checkTest() {



        $content = json_decode(Request::getContent());
        $testId = $content->testId;
        $submittedActions = $content->actions;

        $test = Test::find($testId);
        $ids = explode(',', $test->action_ids);


        $actions = Action::whereIn('id', $ids)->orderByRaw('FIELD (id, ' . implode(',', $ids) . ')')->get();


        $submitted = $test->submitted;


        $reponseActions = [];
        $easyCorrectCount = 0;
        $mediumCorrectCount = 0;
        $difficultCorrectCount = 0;
        foreach($actions as $index => $action) {
            $submittedAction = $submittedActions[$index];
            $submittedVote = $submittedAction->vote;
            $correctCall = $action->getVerifiedOrTopCall();

            if ($correctCall !== false) {
                $submittedCallString = $submittedVote->priority . ":" . $submittedVote->call_id;
                $correctCallString = $correctCall->priorityId . ":" . $correctCall->callId;
                $correct = ($submittedCallString === $correctCallString);

                if ($action->isEasyVerified()) {
                    $easyCorrectCount += 1;
                } elseif ($action->isMediumVerified()) {
                    $mediumCorrectCount += 1;
                } elseif ($action->isDifficultVerified()) {
                    $difficultCorrectCount += 1;
                }
            } else {
                $correct = 'Undetermined';
            }

            echo $index;
            var_dump($correct);

            $reponseActions[] = [
                'id' => $action->id,
                'video_url' => $action->getVideoAttribute(),
                'thumb_url' => $action->thumb_url,
                'correct' => $correct,
                'voteArray' => $action->getVoteArray()
            ];
        }

        $test->submitted = true;
        $test->save();

        return response(
            [
                'testId' => $testId,
                'actions' => $reponseActions,
            ]
        )->withHeaders([
            'Content-Type' => 'text/json; charset=utf-8'
        ]);
    }
}
