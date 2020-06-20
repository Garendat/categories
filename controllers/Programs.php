<?php namespace Cds\Study\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Cds\Study\Models\Program;
use Flash;
use Redirect;
use Cds\Study\Models\Sector;

/**
 * Programs Back-end Controller
 */
class Programs extends Controller
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
        if($moderate = $this->getModerate())  $this->vars['moderate'] = $moderate;
        parent::__construct();
        BackendMenu::setContext('Cds.Study', 'study', 'programs');
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
        });
    }

    public function formFindModelObject($record){
        $model = Program::withOutModerate()->find($record);
        return $model;
    }


    public function getModerate(){
        return !empty(get('moderate', null));
    }

    public function relationExtendConfig($config, $field, $model){
        if($model->isDuplicate() || $model->isUpdateStatus())   $config->view['scope'] = 'getWithOutOriginal';
        parent::relationExtendConfig($config, $field, $model);
    }

    public function listExtendColumns($listWidget){
        if($this->getModerate()){
            $listWidget->addColumns([
                'moderate' => [
                    'label' => 'Предпросмотр',
                    'type' => 'partial',
                    'path' => 'moderate',
                    'clickable' => false
                ]
            ]);
            $listWidget->removeColumn('views_count');
            $listWidget->removeColumn('format_count');
            $listWidget->removeColumn('all_rates');
            $listWidget->removeColumn('using_promocode');
        }
    }

    public function onGetModerateView(){
        $url = post('link', '');
        $id = post('id');
        return [
            '#moderate_view_content' => $this->makePartial('modal_view', ['url' => $url]),
            '#moderate_view_header' => $this->makePartial('modal_footer', ['id' => $id]),
        ];
    }

    public function onSetStatus(){
        $status = post('status', 2);
        $id = post('id', null);
        $program = Program::withOutModerate()->with('original')->find($id);
        if(!empty($program)) $program->saveDuplicate($status);
        if($status == 3) Flash::success('Изменения отклонены');
        if($status == 2) Flash::success('Изменения применены');
        return Redirect::to($_SERVER['REQUEST_URI']);

    }

    public function formExtendFields($formWidget){
        if(!empty($formWidget->model->original_id) ||  ($formWidget->model->isUpdateStatus() && !empty($formWidget->model->id))){
            $formWidget->removeField('status');
        }
    }

    public function onApproveDuplicate($id){
        $model = Program::withOutModerate()->find($id);
        $model->update(input('Program', []));
        $model->saveDuplicate(2);
        Flash::success('Изменения применены');
        return Redirect::to('/backend/cds/study/programs?moderate=Y');
    }

    public function onDiscardDuplicate($id){
        $model = Program::withOutModerate()->find($id);
        $model->saveDuplicate(3);
        Flash::success('Изменения отклонены');
        return Redirect::to('/backend/cds/study/programs?moderate=Y');
    }

    public function onCreateSector($id){
        $program_fields = post('Program');
        $sector = Sector::find($program_fields['sector_id']);
        $program = Program::withOutModerate()->find($id);
        $new_sector = new Sector();
        $new_sector->name = $program_fields['name'];
        if(!empty($sector)){
            $new_sector->parent_id = $sector->id;
        }
        $new_sector->get_parent_programs = true;
        $new_sector->save();
        $program->name = $program_fields['name'];
        $program->sector_id = $new_sector->id;
        $program->save();
        Flash::success('Категория создана');
        return Redirect::refresh();


    }
}
