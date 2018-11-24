<?php
/**
 * Routes.php
 */

Route::group(
    ['prefix' => 'api/'],
    function () {

        Route::get(
            'tournaments/{tournamentId?}/{tournamentChild?}/{boutId?}/{boutChild?}/{actionId?}',
            [
                'uses' =>'Ajslim\FencingActions\Api\Tournaments@index',
            ]
        );
        Route::get(
            'fencers/{fencerId?}/{fencerChild?}/{boutId?}/{boutChild?}/{actionId?}',
            [
                'uses' =>'Ajslim\FencingActions\Api\Fencers@index',
            ]
        );
        Route::get(
            'actions/{actionId?}',
            [
                'uses' =>'Ajslim\FencingActions\Api\Actions@index',
            ]
        );

        Route::get(
            'useractions/{userId}',
            [
                'uses' =>'Ajslim\FencingActions\Api\Actions@userActions',
            ]
        );

    }
);
