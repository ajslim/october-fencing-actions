<?php namespace Ajslim\FencingActions\Controllers;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Traits\SecureController;
use Backend\Facades\BackendAuth;
use BackendMenu;
use Backend\Classes\Controller;
use Redirect;

/**
 * Login Redirect
 *
 */
class LoginRedirect extends Controller
{
    use SecureController;

    public function index()
    {
        $user = BackendAuth::getUser();

        if ($user) {
            if ($user->hasPermission(['ajslim.fencingactions.fie'])
                && $user->id !== 1
            ) {
                return Redirect::to('/');
            }
        }

        return Redirect::to('backend/ajslim/fencingactions/actions');
    }
}
