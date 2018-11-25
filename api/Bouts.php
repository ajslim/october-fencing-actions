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
class Bouts extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index(
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
            return $this->displayModel(Bout::find($boutId), ['actions']);
        }

        return $this->makeDataTablesResponse(Bout::all());
    }
}
