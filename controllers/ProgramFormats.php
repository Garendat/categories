<?php namespace Cds\Study\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Cds\Study\Models\ProgramFormat;

/**
 * Program Formats Back-end Controller
 */
class ProgramFormats extends Controller
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

        BackendMenu::setContext('Cds.Study', 'study', 'programformats');
    }

    public function formFindModelObject($record){
        $model = ProgramFormat::withOutModerate()->find($record);
        return $model;
    }


    public function getModerate(){
        return !empty(get('moderate', null));
    }

    public function relationExtendConfig($config, $field, $model){
        if($model->isDuplicate() || $model->isUpdateStatus())   $config->view['scope'] = 'getWithOutOriginal';
        parent::relationExtendConfig($config, $field, $model);
    }

    public function formExtendFields($formWidget){
        if(!empty($formWidget->model->original_id) ||  ($formWidget->model->isUpdateStatus() && !empty($formWidget->model->id))){
            $formWidget->removeField('status');
        }
    }
}
