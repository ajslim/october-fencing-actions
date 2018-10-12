<?php namespace Ajslim\FencingActions\Behaviors;

use Backend\Classes\Controller;
use Backend\Classes\ControllerBehavior;
use Backend\Classes\FormWidgetBase;
use Backend\Facades\Backend;
use Illuminate\Support\Facades\Input;

/**
 * Class RoutedController
 * @package Aeg\Tempo\Behaviors
 */
class RoutedController extends ControllerBehavior
{
    protected $controller;

    /**
     * Adds the parent relation field to the form and defaults it
     *
     * @param FormWidgetBase $form The form to modify
     *
     * @return void
     */
    public function formExtendFields($form)
    {
        if (isset($this->controller->parentIdFieldName)) {
            $fields = $form->getFields();

            $newFieldsArray = [];
            $newFieldsArray[$this->controller->parentIdFieldName] = [
                'label' => ucfirst($this->controller->parentIdFieldName),
                'type' => 'relation',
                'default' => $this->controller->parentId
            ];

            $form->addFields($newFieldsArray);
        }
    }

    /**
     * Overrides the create function on the form controller
     *
     * @param integer $parentId The id of the parent entity
     * @param null    $context  The context
     *
     * @return mixed
     */
    public function create($parentId = null, $context = null)
    {
        $this->controller->parentId = $parentId;

        // Call the FormController behavior update() method
        return $this->controller
            ->asExtension('FormController')
            ->create($parentId, $context);
    }

    /**
     * An action to filter lists by the parent id
     *
     * URL: controller/p/{parentId}
     *
     * @param integer $parentId The id of the parent entity
     *
     * @return mixed
     */
    public function p($parentId = null)
    {
        $this->controller->parentId = $parentId;
        return $this->controller->run('index', [$parentId]);
    }

    /**
     * RoutedController constructor.
     *
     * @param Controller $controller The controller
     */
    public function __construct($controller)
    {
        parent::__construct($controller);
        $this->controller = $controller;
    }

    /**
     * Overrides list extend
     *
     * @param $query
     */
    public function listExtendQuery($query)
    {
        // Extend the list query to filter by the user id
        if ($this->controller->parentIdFieldName
            && is_numeric($this->controller->parentId)
        ) {
            $query->where(
                $this->controller->parentIdFieldName . "_id",
                $this->controller->parentId
            );
        }
    }

    /**
     * Override the 'create' relation button to navigate to the create page
     *
     * @param integer $id the current record id (the parent of the soon to be
     *                    created child record)
     *
     * @return mixed
     */
    public function nonModalOnRelationButtonCreate($id)
    {
        if (!$this->controller->useModals) {
            $relationField = Input::get('_relation_field');
            return Backend::redirect("ajslim/fencingactions/$relationField/create/$id");
        }
    }
}
