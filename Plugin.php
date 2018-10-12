<?php namespace Ajslim\FencingActions;

use Backend;
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

    }

    /**
     * Boot method, called right before the request route.
     *
     * @return array
     */
    public function boot()
    {

    }

    /**
     * Registers any front-end components implemented in this plugin.
     *
     * @return array
     */
    public function registerComponents()
    {
        return []; // Remove this line to activate

        return [
            'Ajslim\FencingActions\Components\MyComponent' => 'myComponent',
        ];
    }

    /**
     * Registers any back-end permissions used by this plugin.
     *
     * @return array
     */
    public function registerPermissions()
    {
        return [
            'ajslim.fencingactions.some_permission' => [
                'tab' => 'FencingActions',
                'label' => 'Some permission'
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
                'url'         => Backend::url('ajslim/fencingactions/actions'),
                'icon'        => 'icon-bolt',
                'permissions' => ['ajslim.fencingactions.*'],
                'order'       => 500,

                'sideMenu' => [
                    'Actions' => [
                        'label'       => 'Actions',
                        'icon'        => 'icon-bolt',
                        'url'         => Backend::url('ajslim/fencingactions/actions'),
                        'permissions' => ['ajslim.fencingactions.*'],
                    ],
                    'Tags' => [
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
