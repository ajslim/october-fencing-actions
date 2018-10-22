<?php namespace Ajslim\FencingActions\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Bouts Back-end Controller
 */
class Bouts extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController',
        'Ajslim.FencingActions.Behaviors.RoutedController',
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $parentId;
    public $parentIdFieldName = 'tournament';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Ajslim.FencingActions', 'fencingactions', 'bouts');
    }

    /**
     * An override to navigate to the record rather than use the relation modal
     *
     * @param integer $id the id of the record
     *
     * @return mixed
     */
    public function onRelationButtonCreate($id) {
        return parent::nonModalOnRelationButtonCreate($id);
    }
}