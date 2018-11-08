<?php namespace Ajslim\Fencingactions\Controllers;

use Ajslim\FencingActions\Traits\SecureController;
use BackendMenu;
use Backend\Classes\Controller;

/**
 * Fencers Back-end Controller
 *
 * @mixin \Backend\Behaviors\ListController
 * @mixin \Backend\Behaviors\FormController
 * @mixin \Backend\Behaviors\RelationController
 * @mixin \Ajslim\FencingActions\Behaviors\RoutedController
 */
class Fencers extends Controller
{
    use SecureController;

    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController',
        'Ajslim.FencingActions.Behaviors.RoutedController',
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';


    /**
     * Fencers constructor.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Ajslim.FencingActions', 'fencingactions', 'fencers');
    }


    /**
     * An override to navigate to the record rather than use the relation modal
     *
     * @param integer $id the id of the record
     *
     * @return mixed
     */
    public function onRelationButtonCreate($id)
    {
        // The routed controller only supports a single parent so
        // null is passed to the bout creation page
        return parent::nonModalOnRelationButtonCreate(null);
    }
}
