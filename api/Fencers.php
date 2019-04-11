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
class Fencers extends Api
{
    /**
     * The index controller
     *
     * @return array
     */
    public function index(
        $fencerId = null,
        $fencerChild = null,
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

        if ($fencerChild === 'bouts' && $fencerId !== null) {
            return $this->makeDataTablesResponse($this->getFencerBouts($fencerId));
        }

        if ($fencerChild === 'actions' && $fencerId !== null) {
            $bouts = $this->getFencerBouts($fencerId);
            $actions = new Collection();
            foreach ($bouts as $bout) {
                $actions = $actions->merge($bout->actions);
            }

            return $this->makeDataTablesActionResponse($actions);
        }

        if ($fencerChild === 'actions-for' && $fencerId !== null) {
            $bouts = $this->getFencerBouts($fencerId);
            $actions = new Collection();
            foreach ($bouts as $bout) {
                $actions = $actions->merge($bout->actions);
            }

            return $this->makeDataTablesActionResponse($actions);
        }



        if ($fencerId !== null) {
            return $this->makeFencerResponse(Fencer::find($fencerId), ['bouts', 'actions']);
        }

        return $this->makeDataTablesFencerResponse(Fencer::all());
    }

    private function getFencerBouts($fencerId)
    {
        $leftBouts = Bout::where('left_fencer_id', $fencerId)->get();
        $rightBouts = Bout::where('right_fencer_id', $fencerId)->get();

        return $leftBouts->merge($rightBouts);
    }
}
