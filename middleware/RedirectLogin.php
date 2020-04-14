<?php
/**
 * Filename: RedirectLogin.php
 *
 * Tempo
 *
 * @copyright 2019 AEG Europe - All Rights Reserved
 *
 * Last Modified: 11/04/19 17:45
 *
 * All information contained herein is, and remains
 * the property of AEG Europe. Dissemination of this information
 * or reproduction of this material is strictly forbidden unless
 * prior written permission is obtained from AEG Europe.
 */

namespace Ajslim\Fencingactions\Middleware;

use Backend\Facades\BackendAuth;
use Backend\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;

class RedirectLogin
{
    /**
     * The middleware handle function
     *
     * @param Request $request The request
     * @param Closure $next    Teh next Closure
     *
     * @return \Illuminate\Http\RedirectResponse|Response
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $requestUri = $request->getRequestUri();

        $uri = explode('?', $requestUri)[0];

        if ($uri === '/login') {
            return Redirect::to('/backend/backend/auth/signin');
        }

        return $response;
    }
}
