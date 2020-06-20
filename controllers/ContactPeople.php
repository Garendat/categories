<?php namespace Cds\Study\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Cds\Study\Models\ContactPerson;

/**
 * Contact People Back-end Controller
 */
class ContactPeople extends Controller
{

    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Cds.Study', 'study', 'contactpeople');
    }

    public function formFindModelObject($record){
        $model = ContactPerson::withOutModerate()->find($record);
        return $model;
    }


    public function formExtendFields($formWidget){
        if(!empty($formWidget->model->original_id) ||  ($formWidget->model->isUpdateStatus() && !empty($formWidget->model->id))){
            $formWidget->removeField('status');
        }
    }

}
