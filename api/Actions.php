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
use Ajslim\Fencingactions\Models\Tournament;
use Backend\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class Actions extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index(
        $actionId = null
    ) {

        if ($actionId === 'separatingattacks') {
            return $this->separatingAttacks();
        }

        if ($actionId === 'beatvsparry') {
            return $this->beatVsParry();
        }

        if ($actionId === 'possiblecard') {
            return $this->possibleCard();
        }

        if ($actionId === 'possibleline') {
            return $this->possibleLine();
        }

        if ($actionId === 'easy') {
            return $this->allEasy();
        }

        if ($actionId === 'tiedlastactions') {
            return $this->tiedLastActions();
        }

        if ($actionId === 'riposte') {
            return $this->getAction('Riposte');
        }

        if ($actionId === 'attack') {
            return $this->getAction('Attack');
        }

        if ($actionId === 'counterattack') {
            return $this->getAction('Counter Attack');
        }

        if ($actionId === 'line') {
            return $this->getAction('Line');
        }

        if ($actionId === 'remise') {
            return $this->getAction('Remise');
        }

        if ($actionId === 'withtopvote') {
            return $this->withTopVote();
        }

        if ($actionId === 'computerincorrect') {
            return $this->computerIncorrect();
        }

        if ($actionId === 'computercorrect') {
            return $this->computerCorrect();
        }

        if ($actionId === 'computerguessonly') {
            return $this->computerGuessOnly();
        }

        if ($actionId !== null) {
            return $this->makeActionResponse(Action::find($actionId));
        }

        return $this->makeDataTablesActionResponse(Action::all());
    }

    /**
     * The user actions
     *
     * @return array
     */
    public function userActions(
        $userId
    ) {
        return $this->makeDataTablesActionResponse(Action::whereHas('votes', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })->get());
    }

    /**
     * The user actions
     *
     * @return array
     */
    public function tiedLastActions() {

        $results = DB::select(DB::raw('select action_id from
        (select *
         from october_business.ajslim_fencingactions_bouts afb
                  join (
             SELECT a.id as action_id, bout_id
             FROM october_business.ajslim_fencingactions_actions a
                      INNER JOIN (
                 SELECT bout_id as bout_idb, MAX(time) timeb
                 FROM october_business.ajslim_fencingactions_actions
                 GROUP BY bout_id
             ) b ON a.bout_id = b.bout_idb AND a.time = b.timeb
         ) as x
                       on bout_id = afb.id
         where left_score = 14
            or right_score = 14
        ) as z'));

        $actionIds = collect($results)->pluck('action_id')->toArray();

        // Where the top 2 calls are attack from either side, or simultaneous
        return $this->makeDataTablesActionResponse(
            Action::whereIn('id', $actionIds)
                ->get()
        );
    }



    /**
     * The user actions
     *
     * @return array
     */
    public function separatingAttacks() {

        // Where the top 2 calls are attack from either side, or simultaneous
        return $this->makeDataTablesActionResponse(
            Action::whereRaw(
                "INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '2:1:') > 0 "
                    . "AND INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '1:1:') > 0"
            )->orWhereRaw(
                "INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '2:1:') > 0 "
                . "AND INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '0:7:') > 0"
            )->orWhereRaw(
                "INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '1:1:') > 0 "
                . "AND INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '0:7:') > 0"
            )->get()
        );
    }


    /**
     * The user actions
     *
     * @return array
     */
    public function possibleLine() {
        // Where someone has given a point in line
        return $this->makeDataTablesActionResponse(
            Action::where("ordered_calls_cache", "like", '%:5:%')->get()
        );
    }


    /**
     * The user actions
     *
     * @return array
     */
    public function beatVsParry() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::whereRaw(
                "INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '2:1:') > 0 "
                . "AND INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '1:3:') > 0"
            )->orWhereRaw(
                "INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '2:3:') > 0 "
                . "AND INSTR(SUBSTRING_INDEX(ordered_calls_cache, ',', 2), '1:1:') > 0"
            )->get()
        );
    }


    /**
     * The user actions
     *
     * @return array
     */
    public function possibleCard() {
        // Where someone has given a card
        return $this->makeDataTablesActionResponse(
            Action::whereRaw(
                "INSTR(ordered_calls_cache, ':99:') > 0 "
            )->get()
        );
    }

    /**
     * A random riposte
     *
     * @return array
     */
    public function getAction($actionName) {
        return $this->makeDataTablesActionResponse(
            Action::where('confidence_cache', '>=', .80)
                ->where('top_vote_name_cache', '=', $actionName)
                ->get()
        );
    }


    /**
     * The easy verified actions
     *
     * @return array
     */
    public function verifiedEasy() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where('confidence_cache', '>=', .80)
            ->where('is_verified', true)
            ->get()
        );
    }

    /**
     * The unverified Easy actions
     *
     * @return array
     */
    public function unverifiedEasy() {
        return $this->makeDataTablesActionResponse(
            Action::where('confidence_cache', '>=', .80)
                ->where('is_verified', false)
                ->get()
        );
    }


    /**
     * The all Easy actions
     *
     * @return array
     */
    public function allEasy() {
        return $this->makeDataTablesActionResponse(
            Action::where('confidence_cache', '>=', .80)
                ->get()
        );
    }

    /**
     * The verified Medium actions
     *
     * @return array
     */
    public function verifiedMedium() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where('confidence_cache', '<', .80)
                ->where('confidence_cache', '>=', .50)
                ->where('vote_count_cache', '>=', 3)
                ->where('is_verified', false)
                ->get()
        );
    }


    /**
     * The verified hard actions
     *
     * @return array
     */
    public function verifiedHard() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where('confidence_cache', '<', .50)
                ->where('is_verified', false)
                ->get()
        );
    }


    /**
 * The verified hard actions
 *
 * @return array
 */
    public function withTopVote() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where('top_vote_name_cache', '!=', '')
                ->where('top_vote_name_cache', '!=', null)
                ->get()
        );
    }


    /**
     * The verified hard actions
     *
     * @return array
     */
    public function computerIncorrect() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where('top_call_cache', '!=', '')
                ->whereNotNull('top_call_cache')
                ->whereNotNull('computer_guess_call_cache')
                ->whereRaw('top_call_cache != computer_guess_call_cache')
                ->get()
        );
    }


    /**
     * The verified hard actions
     *
     * @return array
     */
    public function computerCorrect() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where('top_call_cache', '!=', '')
                ->whereNotNull('top_call_cache')
                ->whereNotNull('computer_guess_call_cache')
                ->whereRaw('top_call_cache = computer_guess_call_cache')
                ->get()
        );
    }


    /**
     * The verified hard actions
     *
     * @return array
     */
    public function computerGuessOnly() {

        // Where the top 2 calls are attack from left and riposte from the right, or vice versa
        return $this->makeDataTablesActionResponse(
            Action::where(function($query) {
                $query->whereNull('top_call_cache')
                    ->orWhere('top_call_cache', '=', '');
            })
                ->where('is_not_action_vote_count_cache', '=', 0)
                ->whereNotNull('computer_guess_call_cache')
                ->get()
        );
    }
}
