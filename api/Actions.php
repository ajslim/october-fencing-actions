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

        if ($actionId !== null) {
            return $this->displayModel(Action::find($actionId));
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
}
