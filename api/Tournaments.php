<?php
/**
 * API.php
 * The api json frontend controller
 */

namespace Ajslim\FencingActions\Api;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Call;
use Ajslim\Fencingactions\Models\Tournament;
use Backend\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class Tournaments extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index(
        $tournamentId = null,
        $tournamentChild = null,
        $boutId = null,
        $boutChild = null,
        $actionId = null
    ) {
        if ($actionId !== null) {
            return $this->displayModel(Action::find($actionId));
        }

        if ($boutChild === 'actions' && $boutId !== null) {
            return $this->makeDataTablesActionResponse(Bout::find($boutId)->actions);
        }

        if ($boutId !== null) {
            return $this->makeBoutResponse(Bout::find($boutId), ['actions']);
        }

        if ($tournamentChild === 'bouts' && $tournamentId !== null) {
            return $this->makeDataTablesBoutResponse(Tournament::find($tournamentId)->bouts);
        }

        if ($tournamentChild === 'actions' && $tournamentId !== null) {
            $actions = new Collection();
            foreach (Tournament::find($tournamentId)->bouts as $bout) {
                $actions = $actions->merge($bout->actions);
            }

            return $this->makeDataTablesActionResponse($actions);
        }

        if ($tournamentId !== null) {
            return $this->displayModel(Tournament::find($tournamentId), ['bouts', 'actions']);
        }

        return $this->makeDataTablesResponse(Tournament::all());
    }
}
