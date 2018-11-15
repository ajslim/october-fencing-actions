<?php
/**
 * Routes.php
 */

Route::group(
    ['prefix' => 'api/'],
    function () {

        Route::get(
            'tournaments/{tournamentId?}/{bouts?}/{boutId?}/{actions?}/{actionId?}',
            [
                'uses' =>'Ajslim\FencingActions\Api\Tournaments@index',
            ]
        );
    }
);
