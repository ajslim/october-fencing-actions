<?php namespace Ajslim\FencingActions\Controllers;

use Ajslim\FencingActions\Traits\SecureController;
use BackendMenu;
use Backend\Classes\Controller;

/**
 * Tournaments Back-end Controller
 *
 * @mixin \Backend\Behaviors\FormController
 */
class Tournaments extends Controller
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
     * Tournaments constructor.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Ajslim.FencingActions', 'fencingactions', 'tournaments');
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
        return parent::nonModalOnRelationButtonCreate($id);
    }
}
