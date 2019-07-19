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
use Ajslim\FencingActions\Repositories\ActionRepository;
use Backend\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;

/**
 * Api Controller
 */
class Test extends Api
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

        $actions = $easyVerifiedActions->merge($mediumVerifiedActions);
        $actions = $actions->merge($difficultVerifiedActions);
        $actions = $actions->merge($newActions);

        foreach($actions as $action) {
            $action->updateCacheColumns();
        }

        return $this->displayModel($actions);
    }
}
