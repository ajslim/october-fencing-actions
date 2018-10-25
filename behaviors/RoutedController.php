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
        $controller = $this->controller;
        if (isset($controller::$parentFieldName) === true) {
            $fields = $form->getFields();

            $newFieldsArray = [];
            $newFieldsArray[$controller::$parentFieldName] = [
                'label' => ucfirst($controller::$parentFieldName),
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
     * @param mixed $query The query
     *
     * @return void
     */
    public function listExtendQuery($query)
    {
        $controller = $this->controller;
        // Extend the list query to filter by the user id
        if (isset($controller::$parentFieldName) === true
            && is_numeric($this->controller->parentId) === true
        ) {
            $query->where(
                $controller::$parentFieldName . "_id",
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
        if ($this->controller->useModals !== true) {
            $relationField = Input::get('_relation_field');
            $relationSlug = str_replace('_', '', $relationField);
            return Backend::redirect("aeg/tempo/$relationSlug/create/$id");
        }
    }


    /**
     * Breadcrumb maker
     *
     * @return void
     */
    public function renderBreadCrumb()
    {
        $controllerChain = [];

        // Prevents infinite looping
        $maxLength = 10;

        $currentControllerClassName = get_class($this->controller);
        $currentModel = $this->controller->formGetModel();

        $breadCrumbArray = [];

        for ($controlerCount = 0; $controlerCount <= $maxLength; $controlerCount += 1) {
            // Create a url from the class name
            $urlString = strtolower(str_replace('Controllers\\', '', $currentControllerClassName));

            // Direct the url to update page of the specific record
            $urlString .= '/update/' . $currentModel->id;

            // Create the breadcumb
            $url = Backend::url($urlString);
            $breadCrumbArray[] = '<li><a href="' . $url . '">' . $currentModel->name . '</a></li>';

            // Check if the controller and model has a parent
            if (isset($currentControllerClassName::$parentControllerName) === true
                && $currentControllerClassName::$parentFieldName !== null
                && $currentModel->{$currentControllerClassName::$parentFieldName} !== null
            ) {
                // Update current model to the parent and continue the loop
                $currentModel = $currentModel->{$currentControllerClassName::$parentFieldName};
                $currentControllerClassName = $currentControllerClassName::$parentControllerName;
            } else {
                // If the parent doesn't exist it's the top of teh chain
                break;
            }
        }

        $breadCrumbArray = array_reverse($breadCrumbArray);

        foreach ($breadCrumbArray as $breadCrumb) {
            echo $breadCrumb;
        }
    }
}
