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
    public const EASY_COUNT = 4;
    public const MEDIUM_COUNT = 1;
    public const DIFFICULT_COUNT = 1;
    public const NEW_COUNT = 2;

    /**
     * The index controller
     *
     * @return array
     */
    public function index() {
        $easyVerifiedActions = ActionRepository::getEasyVerifiedActions()->paginate(self::EASY_COUNT);
        /** @var Action[] $actions */
        $actions = $easyVerifiedActions;
        $easyIds = $easyVerifiedActions->pluck('id')->toArray();

        if (self::MEDIUM_COUNT !== 0) {
            $mediumVerifiedActions = ActionRepository::getMediumVerifiedActions()->paginate(self::MEDIUM_COUNT);
            $mediumIds = $mediumVerifiedActions->pluck('id')->toArray();
            $actions = $easyVerifiedActions->merge($mediumVerifiedActions);
        }
        if (self::DIFFICULT_COUNT !== 0) {
            $difficultVerifiedActions = ActionRepository::getDifficultVerifiedActions()->paginate(self::DIFFICULT_COUNT);
            $difficultIds = $difficultVerifiedActions->pluck('id')->toArray();
            $actions = $actions->merge($difficultVerifiedActions);
        }

        if (self::NEW_COUNT !== 0) {
            $newActions = ActionRepository::getActionsWithFewVotes()->paginate(self::NEW_COUNT);
            $newIds = $newActions->pluck('id')->toArray();
            $actions = $actions->merge($newActions);
        }






        foreach($actions as $action) {
            $action->updateCacheColumns();
        }

        $shuffledActions = $actions->shuffle()->all();

        $questions = [];
        $ids = [];

        $count = 0;
        foreach($shuffledActions as $action) {
            $ids[] = $action->id;
            $count += 1;
            $questions[] = [
                'video_url' => $action->getVideoAttribute(),
                'left_fencer_name' => $action->getLeftnameAttribute(),
                'right_fencer_name' => $action->getRightnameAttribute(),
                'tournament' => $action->getRightnameAttribute(),
                'thumb_url' => $action->thumb_url,
                'number' => $count,
            ];
        }

        $test = Test::create([
            'action_ids' => implode(',', $ids),
            'easy_ids' => implode(',', $easyIds),
            'medium_ids' => implode(',', $mediumIds),
            'difficult_ids' => implode(',', $difficultIds),
            'new_ids' => implode(',', $newIds),
        ]);

        return response(
            json_encode([
                'id' => $test->id,
                'actions' => $questions,
                'enableFencingDb' => true
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

        $responseActions = [];
        $easyCorrectCount = 0;
        $easyCount = 0;
        $mediumCorrectCount = 0;
        $mediumCount = 0;
        $difficultCorrectCount = 0;
        $difficultCount = 0;
        $newCount = 0;

        foreach($actions as $index => $action) {
            if (isset($submittedActions[$index]) === false) {
                continue;
            }

            $submittedAction = $submittedActions[$index];
            $submittedVote = $submittedAction->vote;

            $correctCall = null;
            $correctCall = $action->getVerifiedCallObject();

            $correct = null;
            $callDifficulty = null;
            if ($correctCall !== false) {
                if (isset($submittedVote->priority) === true) {
                    $submittedCallString = $submittedVote->priority . ":" . $submittedVote->call_id;
                    $correctCallString = $correctCall->priorityId . ":" . $correctCall->callId;
                    $correct = ($submittedCallString === $correctCallString);
                } elseif (isset($submittedVote->card_for) === true) {
                    $submittedCallString = $submittedVote->card_for . ":" . Call::CARD_ID;
                    $correctCallString = $correctCall->priorityId . ":" . Call::CARD_ID;
                    $correct = ($submittedCallString === $correctCallString);
                }

                if (in_array($action->id, explode(',', $test->easy_ids)) === true) {
                    $callDifficulty = 1;
                    $easyCount += 1;
                    if ($correct) {
                        $easyCorrectCount += 1;
                    }
                } elseif (in_array($action->id, explode(',', $test->medium_ids)) === true) {
                    $callDifficulty = 2;
                    $mediumCount += 1;
                    if ($correct) {
                        $mediumCorrectCount += 1;
                    }
                } elseif (in_array($action->id, explode(',', $test->difficult_ids)) === true) {
                    $callDifficulty = 3;
                    $difficultCount += 1;
                    if ($correct) {
                        $difficultCorrectCount += 1;
                    }
                }
            } else {
                $newCount += 1;
            }

            $responseAction = [
                'id' => $action->id,
                'index' => $index,
                'video_url' => $action->getVideoAttribute(),
                'thumb_url' => $action->thumb_url,
                'correct' => $correct,
                'submittedVote' => $submittedVote,
                'voteArray' => $action->getVoteArray(),
                'difficulty' => $callDifficulty,
            ];

            if ($correctCall !== false) {
                $responseAction['correctCall'] = [
                    'call_id' => $correctCall->callId,
                    'priority' => $correctCall->priorityId
                ];
            }

            $responseActions[] = $responseAction;
        }

        $test->submitted = true;
        $test->save();

        $testResult = 'test';
        if (($easyCorrectCount / $easyCount) < .95) {
            $testResult = 'test-fail';
        } elseif (
            $mediumCount !== 0
            && $mediumCorrectCount === $mediumCount
            && $difficultCount !== 0
            && $difficultCorrectCount === $difficultCount
        ) {
            // No perfect tests for people who don't get any hard or medium calls
            $testResult = 'test-perfect';
        } else {
            $testResult = 'test-pass';
        }

        $actionIds = [];
        foreach ($responseActions as $action) {
            $actionIds[] = $action['id'];
            $this->saveVote($action['submittedVote'], $action['id'], $testResult);
        }

        return response(
            [
                'testId' => $testId,
                'actions' => $responseActions,
                'votedActions' => $actionIds,
                'easyCorrectCount' => $easyCorrectCount,
                'mediumCorrectCount' => $mediumCorrectCount,
                'difficultCorrectCount' => $difficultCorrectCount,
                'easy_count' => $easyCount,
                'medium_count' => $mediumCount,
                'difficult_count' => $difficultCount,
                'new_count' => $newCount,
            ]
        )->withHeaders([
            'Content-Type' => 'text/json; charset=utf-8'
        ]);
    }


    function saveVote($submittedVote, $actionId, $refereeLevel)
    {
        $vote = Vote::create([
            'action_id' => $actionId
        ]);

        if (isset($submittedVote->priority) === true
            && isset($submittedVote->call_id) === true
            && (
                $submittedVote->priority !== '0'
                || $submittedVote->call_id === '7'
            )

        ) {
            $vote->call_id = intval($submittedVote->call_id);
            $vote->priority = intval($submittedVote->priority);
        }

        if (isset($submittedVote->card_for) === true && (int) $submittedVote->card_for !== 0) {
            $vote->card_for = intval($submittedVote->card_for);
        }

        if (isset($submittedVote->difficulty) === true) {
            $vote->difficulty = intval($submittedVote->difficulty);
        }
        if (isset($submittedVote->vote_comment_id) === true) {
            $vote->vote_comment_id = intval($submittedVote->vote_comment_id);
        } else {
            $vote->vote_comment_id = 1;
        }

        if (isset( $submittedVote->difficulty) === true) {
            $vote->difficulty = $submittedVote->difficulty;
        }

        // Log the ip address of the vote, just in case some craziness happens
        $vote->ip_address = $_SERVER['REMOTE_ADDR'];

        // If it's proxied through refereetest
        if ($vote->ip_address === '104.248.164.27') {
            $vote->ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        $vote->referee_level = $refereeLevel;


        $vote->save();

        $vote->action->updateCacheColumns();
    }
}
