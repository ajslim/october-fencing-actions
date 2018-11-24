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
}
