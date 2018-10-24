<?php namespace Ajslim\FencingActions\Controllers;

use Ajslim\FencingActions\Models\Bout;
use BackendMenu;
use Backend\Classes\Controller;
use Redirect;

/**
 * Bouts Back-end Controller
 *
 * @mixin \Backend\Behaviors\FormController
 * @mixin \Backend\Behaviors\ListController
 * @mixin \Backend\Behaviors\RelationController
 * @mixin \Ajslim\FencingActions\Behaviors\RoutedController
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


    /**
     * Bouts constructor.
     */
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
    public function onRelationButtonCreate($id)
    {
        return parent::nonModalOnRelationButtonCreate($id);
    }


    /**
     * Reverses the fencers in the parent bout
     *
     * @param integer $id The action Id
     *
     * @return Redirect
     */
    public function onReverseBoutFencers($id)
    {
        /* @var Bout $bout */
        $bout = Bout::find($id);

        // Reverse the fencers in the parent bout
        $bout->reverseFencers();

        return Redirect::refresh();
    }
}
