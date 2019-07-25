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
use Ajslim\FencingActions\Repositories\ActionRepository;
use Backend\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;

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

        $testId = Input::get('testId');

        $test = Test::find($testId);
        $ids = explode(',', $test->action_ids);

        $submittedAnswers = Input::get('answers');
        $actions = Action::whereIn('id', $ids)->orderByRaw('FIELD (id, ' . implode(',', $ids) . ')')->get();

        $submitted = $test->submitted;

        $response = [];
        foreach($actions as $index => $action) {
            $submittedAnswer = $submittedAnswers[$index];
            $correctCall = $action->getVerifiedOrTopCall();

            if ($correctCall !== false) {
                $correctCallString = $correctCall->priorityId . ":" . $correctCall->callId;
                $correct = ($submittedAnswer === $correctCallString);
            } else {
                $correct = true;
            }

            $response[] = [
                'id' => $action->id,
                'video_url' => $action->getVideoAttribute(),
                'thumb_url' => $action->thumb_url,
                'correct' => $correct,
            ];
        }

        $test->submitted = true;
        $test->save();

        return response(
            $response
        )->withHeaders([
            'Content-Type' => 'text/json; charset=utf-8'
        ]);
    }
}
