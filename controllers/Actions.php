<?php namespace Ajslim\FencingActions\Controllers;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use BackendMenu;
use Backend\Classes\Controller;
use Redirect;

/**
 * Actions Back-end Controller
 *
 * @mixin \Backend\Behaviors\FormController
 * @mixin \Backend\Behaviors\ListController
 * @mixin \Backend\Behaviors\RelationController
 */
class Actions extends Controller
{
    /**
     * @var array $implement The implemented behaviors
     */
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';


    /**
     * Actions constructor.
     */
    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Ajslim.FencingActions', 'fencingactions', 'actions');
    }


    /**
     * Updates the gfycat fields on save
     *
     * @param null $context The Context
     *
     * @return void
     */
    public function update_onSave($context = null)
    {
        parent::update_onSave($context);
        $this->vars['value'] = $this->formGetModel()->gfycat_id;
    }


    /**
     * Reverses the fencers in the parent bout
     *
     * @return Redirect
     */
    public function onReverseBoutFencers($id)
    {
        $action = Action::find($id);
        $bout = Bout::find($action->bout_id);

        // Reverse the fencers in the parent bout
        $bout->reverseFencers();

        return Redirect::refresh();
    }
}
