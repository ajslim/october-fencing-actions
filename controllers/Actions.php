<?php namespace Ajslim\FencingActions\Controllers;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Traits\SecureController;
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
    use SecureController;

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
     * Updates the video fields on save
     *
     * @param null $context The Context
     *
     * @return void
     */
    public function update_onSave($context = null)
    {
        parent::update_onSave($context);
        $this->vars['value'] = $this->formGetModel()->video_url;
    }


    /**
     * Reverses the fencers in the parent bout
     *
     * @param integer $id The action Id
     *
     * @return Redirect
     */
    public function onReverseFencers($id)
    {
        /* @var Action $action */
        $action = Action::find($id);
        $action->reverseFencers();

        return Redirect::refresh();
    }
}
