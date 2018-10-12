<?php namespace Ajslim\FencingActions\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Actions Back-end Controller
 */
class Actions extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend.Behaviors.RelationController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Ajslim.FencingActions', 'fencingactions', 'actions');
    }

    public function update_onSave($context = null)
    {
        parent::update_onSave($context);
        $this->vars['value'] = $this->formGetModel()->gfycat_id;
    }
}
