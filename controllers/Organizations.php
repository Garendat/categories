<?php namespace Cds\Study\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Cds\Study\Models\Organization;
use Cds\Study\Models\Program;
use Cds\Study\Widgets\CategoryTree;
use Flash;
use Redirect;
use Cds\Study\Models\UserAction;

/**
 * Organizations Back-end Controller
 */
class Organizations extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController',
        'Backend\Behaviors\RelationController',
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';
    public $relationConfig = 'config_relation.yaml';

    public function __construct()
    {
        $this->vars['moderate'] = $this->getModerate();
        $this->vars['request'] = $this->getRequest();
        parent::__construct();

        BackendMenu::setContext('Cds.Study', 'study', 'organizations');
    }

    public function onChangeStatus()
    {
        $program = Program::find(post('id'));

        $program->update(['status' => post('status')]);

        return ["#statuses-{$program->id}" => $this->makePartial('status_program', ['record' => $program])];
    }

    public function listExtendQuery($query){
       $query->when($this->getModerate(), function($query){
          return $query->getDirtyForController();
       })->when($this->getRequest(), function($query){
           return $query->whereHas('requests', function($query){
               return $query->byValue(1);
           });
       });
    }

    protected function getRequest(){
        return !empty(get('request', null));
    }

    public function formFindModelObject($record){
        $model = Organization::withOutModerate()->find($record);
        return $model;
    }


    public function getModerate(){
        return !empty(get('moderate', null));
    }

    public function relationExtendConfig($config, $field, $model){
        if($model->isDuplicate() || $model->isUpdateStatus())   $config->view['scope'] = 'getWithOutOriginal';
        parent::relationExtendConfig($config, $field, $model);

    }

    public function moderateAddAndRemoveColumns($listWidget){
        $listWidget->addColumns([
            'moderate' => [
                'label' => 'Предпросмотр',
                'type' => 'partial',
                'path' => 'moderate_organization',
                'align' => 'center',
                'sortable' => false,
                'clickable' => false
            ]
        ]);
        $listWidget->removeColumn('tariff[name]');
        $listWidget->removeColumn('realm[name]');
    }

    public function requestAddAndRemoveColumns($listWidget){
        $listWidget->addColumns([
            'request' => [
                'label' => 'Заявки',
                'type' => 'partial',
                'path' => 'request_organization',
                'clickable' => false
            ]
        ]);
    }


    public function listExtendColumns($listWidget){
        if($this->getModerate()) $this->moderateAddAndRemoveColumns($listWidget);
        if($this->getRequest()) $this->requestAddAndRemoveColumns($listWidget);

    }

    public function formExtendFields($formWidget){
        if(!empty($formWidget->model->original_id) ||  ($formWidget->model->isUpdateStatus() && !empty($formWidget->model->id))){
            $formWidget->removeField('categories');
            $formWidget->removeField('status');
            $formWidget->removeField('programs');
            }
    }




    public function onGetModerateView(){
        $url = post('link', '');
        $organization_id = post('organization_id');
        return [
            '#moderate_view_content' => $this->makePartial('modal_view', ['url' => $url]),
            '#moderate_view_header' => $this->makePartial('modal_footer', ['organization_id' => $organization_id]),
            ];
    }

    public function onSetStatus(){
        $status = post('status', 2);
        $organization_id = post('organization_id', null);
        $organization = Organization::withOutModerate()->with('original')->find($organization_id);
        if(!empty($organization)) $organization->saveDuplicate($status);
        if($status == 3) Flash::success('Изменения отклонены');
        if($status == 2) Flash::success('Изменения применены');
        return Redirect::to($_SERVER['REQUEST_URI']);

    }

    public function onApproveDuplicate($id){
        $model = Organization::withOutModerate()->find($id);
        $model->update(input('Organization', []));
        $model->saveDuplicate(2);
        Flash::success('Изменения применены');
        return Redirect::to('/backend/cds/study/organizations?moderate=Y');
    }

    public function onDiscardDuplicate($id){
        $model = Organization::withOutModerate()->find($id);
        $model->saveDuplicate(3);
        Flash::success('Изменения отклонены');
        return Redirect::to('/backend/cds/study/organizations?moderate=Y');
    }

    public function onGetRequestView(){
        $id = post('id');
        $organization = Organization::with(['requests' => function ($query){
            return $query->byValue(1)->with('user');
        }])->with('user')->find($id);
        $user_name = 'Пользователя нет';
        if(!empty($organization->user)) $user_name = $organization->user->full_name;

        return ['#request_view_content' => $this->makePartial('modal_request_view', ['requests' => $organization->requests]),
            '#modal-title-request-name' => $organization->name, '#modal-title-request-user' => $user_name,
        ];
    }

    public function onRequest(){
        $status = post('status');
        $action = UserAction::find(post('id'));
        if($status){
            $organization = Organization::find($action->object_id);
            $organization->user_id = $action->user_id;
            $organization->save();
            $action->value = 2;
            $action->save();
            Flash::success('Организация успешно присвоена');
            return Redirect::to('/backend/cds/study/organizations?request=Y');
        } else {
            $action->value = 3;
            $action->save();
            Flash::success('Заявка отклонена');
            return ["#request_{$action->id}" => ''];
        }
    }

}
