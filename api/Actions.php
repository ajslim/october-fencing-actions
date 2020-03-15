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

        if ($actionId === 'withtopvote') {
            return $this->withTopVote();
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
            Action::whereRaw(
                "INSTR(ordered_calls_cache, ':5:') > 0 "
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
}
