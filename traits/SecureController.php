<?php namespace Ajslim\FencingActions\Traits;

use Backend\Facades\Backend;

trait SecureController
{

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';


    public function isAdmin()
    {
        return $this->user->hasPermission([
            'ajslim.fencingactions.admin'
        ]);
    }

    public function index()
    {
        $this->vars['isAdmin'] = $this->isAdmin();
        return parent::index();
    }

    public function relationRenderToolbar()
    {
        if($this->isAdmin() !== true) {
            return;
        }
        return parent::relationRenderToolbar();
    }

    public function preview($id)
    {
        $this->vars['isAdmin'] = $this->isAdmin();
        return parent::preview($id);
    }

    public function update($id)
    {
        if($this->isAdmin() !== true) {
            $url = $_SERVER['REQUEST_URI'];

            $previewUrl = str_replace('/update', '/preview', $url);
            $previewUrl = str_replace('/backend/', '', $previewUrl);
            return Backend::redirect($previewUrl);
        }
        return parent::update($id);
    }

    public function create()
    {
        if($this->isAdmin() !== true) {
            \Flash::error('You do not have permission to create');
            $url = $_SERVER['REQUEST_URI'];

            $indexUrl = str_replace('/create', '', $url);
            $indexUrl = str_replace('/backend/', '', $indexUrl);
            return Backend::redirect($indexUrl);
        }
        return parent::create();
    }

    public function index_onDelete()
    {
        if($this->isAdmin() !== true) {
            \Flash::error('You do not have permission to delete');
        } else {
            return parent::index_onDelete();
        }
    }

    public function update_onDelete()
    {
        if($this->isAdmin() !== true) {
            \Flash::error('You do not have permission to delete');
        } else {
            return parent::update_onDelete();
        }
    }
}
