<?php namespace Ajslim\FencingActions;

use Backend;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use System\Classes\CombineAssets;
use System\Classes\PluginBase;

/**
 * FencingActions Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails()
    {
        return [
            'name'        => 'FencingActions',
            'description' => 'No description provided yet...',
            'author'      => 'Ajslim',
            'icon'        => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     *
     * @return void
     */
    public function register()
    {
        $this->registerConsoleCommand('ajslim.UpdateFencersFromFie', 'Ajslim\FencingActions\Console\UpdateFencersFromFie');
        $this->registerConsoleCommand('ajslim.JsonUpdateFencersFromFie', 'Ajslim\FencingActions\Console\JsonUpdateFencersFromFie');
        $this->registerConsoleCommand('ajslim.JsonUpdateFencersDetails', 'Ajslim\FencingActions\Console\JsonUpdateFencersDetails');
        $this->registerConsoleCommand('ajslim.getAjslimGfycats', 'Ajslim\FencingActions\Console\getAjslimGfycats');
        $this->registerConsoleCommand('ajslim.UpdateTournamentsFromFie', 'Ajslim\FencingActions\Console\UpdateTournamentsFromFie');
        $this->registerConsoleCommand('ajslim.JsonUpdateTournamentsFromFie', 'Ajslim\FencingActions\Console\JsonUpdateTournamentsFromFie');
        $this->registerConsoleCommand('ajslim.UpdateBoutsFromFie', 'Ajslim\FencingActions\Console\UpdateBoutsFromFie');
        $this->registerConsoleCommand('ajslim.JsonUpdateBoutsFromFie', 'Ajslim\FencingActions\Console\JsonUpdateBoutsFromFie');
        $this->registerConsoleCommand('ajslim.CacheBoutNames', 'Ajslim\FencingActions\Console\CacheBoutNames');
        $this->registerConsoleCommand('ajslim.RemoveDuplicateBouts', 'Ajslim\FencingActions\Console\RemoveDuplicateBouts');
        $this->registerConsoleCommand('ajslim.UpdateFencerPhotos', 'Ajslim\FencingActions\Console\UpdateFencerPhotos');
        $this->registerConsoleCommand('ajslim.AnalyzeBout', 'Ajslim\FencingActions\Console\AnalyzeBout');
        $this->registerConsoleCommand('ajslim.ProfileTool', 'Ajslim\FencingActions\Console\ProfileTool');
        $this->registerConsoleCommand('ajslim.CreateActionsForBouts', 'Ajslim\FencingActions\Console\CreateActionsForBouts');
        $this->registerConsoleCommand('ajslim.CreateActionsForBout', 'Ajslim\FencingActions\Console\CreateActionsForBout');
        $this->registerConsoleCommand('ajslim.SearchYoutubeUrlsForTournament', 'Ajslim\FencingActions\Console\SearchYoutubeUrlsForTournament');
        $this->registerConsoleCommand('ajslim.Refresh', 'Ajslim\FencingActions\Console\Refresh');
        $this->registerConsoleCommand('ajslim.getrules', 'Ajslim\FencingActions\Console\GetRules');
        $this->registerConsoleCommand('ajslim.updateactionscache', 'Ajslim\FencingActions\Console\UpdateActionsCache');
        $this->registerConsoleCommand('ajslim.addlightstoactions', 'Ajslim\FencingActions\Console\AddLightsToActions');
        $this->registerConsoleCommand('ajslim.addcameratoactions', 'Ajslim\FencingActions\Console\AddCameraMovementToActions');
        $this->registerConsoleCommand('ajslim.analyzeaction', 'Ajslim\FencingActions\Console\AnalyzeAction');
        $this->registerConsoleCommand('ajslim.getfencervelocities', 'Ajslim\FencingActions\Console\GetFencerVelocities');
        $this->registerConsoleCommand('ajslim.createmadlibsclips', 'Ajslim\FencingActions\Console\CreateMadlibsClips');
        $this->registerConsoleCommand('ajslim.trackfencersonactions', 'Ajslim\FencingActions\Console\TrackFencersOnActions');
        $this->registerConsoleCommand('ajslim.downloadaudioforbouts', 'Ajslim\FencingActions\Console\DownloadAudioForBouts');
        $this->registerConsoleCommand('ajslim.createaudioforactions', 'Ajslim\FencingActions\Console\CreateAudioForActions');
        $this->registerConsoleCommand('ajslim.bladecontactsonactions', 'Ajslim\FencingActions\Console\BladeContactsOnActions');
        $this->registerConsoleCommand('ajslim.guesscallonaction', 'Ajslim\FencingActions\Console\GuessCallOnAction');

        CombineAssets::registerCallback(
            function ($combiner) {
                $combiner->registerBundle('~/plugins/ajslim/fencingactions/components/voteonaction/assets/styles.less');
            }
        );
    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {
        $this->app['Illuminate\Contracts\Http\Kernel']
            ->pushMiddleware('Ajslim\Fencingactions\Middleware\RedirectLogin');
    }



    /**
     * Register schedule method
     *
     * @param Schedule $schedule The schedule
     *
     * @return void
     */
    public function registerSchedule($schedule)
    {

    }


    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return [
            'Ajslim\FencingActions\Components\VoteOnAction' => 'voteOnAction',
            'Ajslim\FencingActions\Components\Browse' => 'browse',
            'Ajslim\FencingActions\Components\MinutoScore' => 'minutoScore',
        ]; // Remove this line to activate
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'ajslim.fencingactions.admin' => [
                'tab' => 'FencingActions',
                'label' => 'Admin Permissions'
            ],
            'ajslim.fencingactions.view' => [
                'tab' => 'FencingActions',
                'label' => 'View Permissions'
            ],
            'ajslim.fencingactions.fie' => [
                'tab' => 'FencingActions',
                'label' => 'FIE Referee'
            ],
        ];
    }

    /**
     * Registers back-end navigation items for this plugin.
     *
     * @return array
     */
    public function registerNavigation()
    {
        return [
            'fencingactions' => [
                'label'       => 'FencingActions',
                'url'         => Backend::url('ajslim/fencingactions/loginredirect'),
                'icon'        => 'icon-bolt',
                'permissions' => ['ajslim.fencingactions.*'],
                'order'       => 500,

                'sideMenu' => [
                    'tournaments' => [
                        'label'       => 'Tournaments',
                        'icon'        => 'icon-bolt',
                        'url'         => Backend::url('ajslim/fencingactions/tournaments'),
                        'permissions' => ['ajslim.fencingactions.*'],
                    ],
                    'bouts' => [
                        'label'       => 'Bouts',
                        'icon'        => 'icon-bolt',
                        'url'         => Backend::url('ajslim/fencingactions/bouts'),
                        'permissions' => ['ajslim.fencingactions.*'],
                    ],
                    'actions' => [
                        'label'       => 'Actions',
                        'icon'        => 'icon-bolt',
                        'url'         => Backend::url('ajslim/fencingactions/actions'),
                        'permissions' => ['ajslim.fencingactions.*'],
                    ],
                    'fencers' => [
                        'label'       => 'Fencers',
                        'icon'        => 'icon-bolt',
                        'url'         => Backend::url('ajslim/fencingactions/fencers'),
                        'permissions' => ['ajslim.fencingactions.*'],
                    ],
                    'tags' => [
                        'label'       => 'Tags',
                        'icon'        => 'icon-bolt',
                        'url'         => Backend::url('ajslim/fencingactions/tags'),
                        'permissions' => ['ajslim.fencingactions.*'],
                    ],

                ]
            ],
        ];
    }
}
