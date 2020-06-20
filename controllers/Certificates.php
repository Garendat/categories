<?php namespace Cds\Study\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Cds\Study\Models\Certificate;

/**
 * Certificates Back-end Controller
 */
class Certificates extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Cds.Study', 'study', 'certificates');
    }

    public function formFindModelObject($record){
        $model = Certificate::withOutModerate()->find($record);
        return $model;
    }


    public function formExtendFields($formWidget){
        if(!empty($formWidget->model->original_id) ||  ($formWidget->model->isUpdateStatus() && !empty($formWidget->model->id))){
            $formWidget->removeField('status');
        }
    }
}
