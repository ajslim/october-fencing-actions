<?php namespace Ajslim\Fencingactions\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Fencers Back-end Controller
 */
class Fencers extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Ajslim.FencingActions', 'fencingactions', 'fencers');
    }
}
