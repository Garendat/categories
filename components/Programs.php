<?php namespace Cds\Study\Components;

use Cds\Study\Models\ObjectType;
use Cds\Study\Models\ObjectTypeAllow;
use Cds\Study\Models\ObjectValue;
use Cds\Study\Models\Program;
use Cds\Study\Models\Organization;
use Cds\Study\Models\Sector;
use Cds\Study\Models\Promocode;
use ApplicationException;
use October\Rain\Support\Facades\Flash;
use Illuminate\Support\Facades\Redirect;
use Cds\Study\Models\ProgramFormat;
use Illuminate\Support\Facades\Lang;
use Auth;
use Response;
use BackendAuth;
use Session;

class Programs extends ComponentBase
{
    public $variant = 'lk_list';

    public function componentDetails()
    {
        return [
            'name' => 'Programs Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function init(){
        $this->addComponent('UserActions', 'UserActions', []);
        $this->addComponent('Sectors', 'Sectors', []);
    }

    public function onRun(){
        if(!empty($this->property('moderate'))){
            if(!BackendAuth::check()) return $this->controller->run('404');
        }

        $mode = $this->property('mode');
        //dd($mode);

        if ($mode == 'program_detail' && $id = (int) $this->controller->param('id')) {
            $data = Program::find($id);
            if (empty($data)) return Response::make($this->controller->run('404')->getContent(), 404);
        }
    }

    public function onRenderList()
    {
        return $this->property('programs');
    }

    public function onRenderLkList()
    {
        $program = $this->property('programs');
        $value = !empty($program) ? $program->count() : 0;
        $count = $this->onGetCount($value);
        return ['data' => $this->property('programs'), 'count' => $count];
    }

    public function onRenderSearchResult()
    {
        return ['programs' => $this->property('programs')];
    }

    public function onRenderLkEdit()
    {
        $id = $this->property('id');
        $sectors_one = Sector::whereLvl(1)->get()->toArray();
        $all_parents = [];
        $sectors_two = [];
        $program_formats_code = ProgramFormat::NAME_CODES;
        $program_options = ObjectTypeAllow::getParamsByClass(Program::class);
        $program_options_ids = ObjectTypeAllow::getParamsByClass(Program::class)->pluck('id')->toArray();
        $program_options = $program_options->toArray();
        $program_names = ProgramFormat::NAMES;
        $term_types = ProgramFormat::TERM_TYPES;
        $exists_format = [];
        $exists_parameters = [];
        $duplicate_program = null;
        if ($this->property('action') == 'edit') {
            $original_program = Program::withOutModerate()->with(['sector', 'parameters'])->find($id);
            $duplicate_program = $original_program->getOriginalOrDuplicate()->load([
                'program_formats'=> function($q){return $q->getWithOutOriginal();   }]);

            foreach($original_program->parameters as $parameter) {
                if (!empty($parameter->parameter_id) && in_array($parameter->parameter_id, $program_options_ids))
                    $exists_parameters[$parameter->parameter_id] = $parameter;
            }
            if(!empty($duplicate_program->program_formats)){
                foreach($duplicate_program->program_formats as $format) {
                    if (!empty($format->code) && in_array($format->code, $program_formats_code))
                        $exists_format[$format->code] = $format;
                }
            }
            if(!empty($duplicate_program->sector)){
                $all_parents = $duplicate_program->sector->getParentsInlineTreeIds();
                $sectors_two = Sector::where('lvl', 2)->whereIn('parent_id', $all_parents)->get()->toArray();
            }
            $organization = Organization::withOutModerate()->findOrNew($original_program->organization_id);
        } elseif ($this->property('action') == 'add') {
            $organization = Organization::withOutModerate()->findOrNew($id);
            $original_program = new Program();
        }
        return [
            'original_program' => $original_program, 'organization' => $organization, 'sectors_one' => $sectors_one,
            'sectors_two' => $sectors_two, 'all_parents' => $all_parents, 'program_formats_code' => $program_formats_code,
            'program_names' => $program_names,
            'exists_format' => $exists_format, 'term_types' => $term_types, 'program_options' => $program_options,
            'exists_parameters' => $exists_parameters, 'duplicate_program' => $duplicate_program ];
    }

    public function onGetSectors()
    {
        $sector = post('sector', []);
        if(empty($sector) || empty($sector[0]))
            throw new ApplicationException('Выберите категорию');
        $children = Sector::where('parent_id', $sector[0])->get()->pluck('name', 'id');
    
        return $children;
    }

    public function onGetNames(){
        $sectors = post('sector', []);
        $sector = null;
        while(true){
            if(!empty($sector) && is_numeric($sector)) break;
            elseif(empty($sectors))
                throw new ApplicationException('Выберите категорию');
            $sector = array_pop($sectors);
        }
        $children_ids = Sector::getChildrenInlineTreeStatic($sector);
        $children_ids[] = $sector;
        $categories = Sector::where('has_child', 0)->whereIn('id', $children_ids)
            ->get()
            ->map(function ($category) {
                return ['name' => $category->name];
            });
            //->lists('name');
        // $result = [];
        // foreach($categories as $category){
        //     $result[] = ['name' => $category];
        // }
        return $categories;
    }

    public function onUpdate()
    {
        $sectors = post('sector', []);
        $sector = null;
        while(true){
            if(!empty($sector) && is_numeric($sector)) break;
            elseif(empty($sectors))
                throw new ApplicationException('Выберите категорию');
            $sector = array_pop($sectors);
        }
        $id = post('program_id') ?: null;
        $session_key = post('_session_key');
        $program = $this->queryOrganization()->findOrNew($id);
        if(empty(post('checked_formats', []))){
            throw new ApplicationException('Необходимо заполнить хотя бы 1 формат обучения');
        }
        if(empty($program->id)){
            $program = $program->fill(post() + ['sector_id' => $sector]);
            foreach(array_keys(post('checked_formats', [])) as $code) {
                $program_format = new ProgramFormat();
                $program_format->fill(['code' => $code] + post($code, []))->save();
                $program->program_formats()->add($program_format, $session_key);
            }
            $program->save(null, $session_key);
        }
        else {
            $checked_formats = array_keys(post('checked_formats', []));
            foreach($checked_formats as $code) {
                $format = $program->program_formats->where('code', $code)->first();
                if(empty($format)) ProgramFormat::create(post($code) + ['code' => $code, 'program_id' => $program->id]);
                else                $format->updateModelOrCreateDuplicate(post($code)  + ['code' => $code]);
            }
            ProgramFormat::withOutModerate()->whereNotIn('code', $checked_formats)->where('program_id', $program->id)->get()->each(function($model){
                $model->deleteWithOriginalOrDuplicate();
            });
            $program->updateModelOrCreateDuplicate(post() + ['sector_id' => $sector]);
        }
        if(!empty($options = post('options')))
            ObjectValue::createWithModel($options, Program::class, $program->getOriginalId());

        // Flash::success('Данные успешно сохранены');
        // return Redirect::to($program->link_edit);

        Session::put('org_id', $program->organization_id);
        if($id) {
            Flash::success('Изменения успешно сохранены');
            
            return Redirect::to('/lk/organizations');
        } else {
            Flash::success('Программа успешно создана');
            return Redirect::to('/lk/organizations');
        }

    }

    public function onRenderModalDeleteProgram($params)
    {
        $program = $this->queryOrganization()->find($params['id']);
        if(empty($program))
            throw new ApplicationException('Вы не можете удалять чужую программу');
        $organization = $program->organization;
        return ['organization' => $organization, 'program' => $program];
    }

    public function onDeleteProgram()
    {
        if(mb_strtolower(post('confirm')) !== 'подтверждаю')
            throw new ApplicationException('Не правильно подтверждено удаление');
        $program = $this->queryOrganization()->find(post('program_id'));
        if(!empty($program)){
            $program->deleteWithOriginalOrDuplicate();
            Flash::success('Программа успешно удалена');
            return Redirect::to('/lk/organizations');
        }
        Flash::success('Программа не найдена');
        return;

    }


    public function onRenderView()
    {
        $program = Program::with(['organization', 'program_formats', 'rates', 'my_rate', 'views'])->find($this->property('id'));
        $this->page->title = $program->name;
        $program->createView();
        $ids = [$program->sector_id];
        $similar_programs = $this->cityFilter()->getSamePrograms([$program->sector_id])->where('id', '<>', $program->organization_id)->with(['programs' => function ($query) use ($ids){
            return $query->notFictive()->whereIn('sector_id', $ids)->whereHas('program_formats')->with(['program_formats', 'rates', 'views']);
        }])->with([ 'rates', 'views'])
            ->inRandomOrder()->limit(3)->get()->each(function($model){
                $model->count_programs = $model->programs->count();
        });
        return ['program' => $program, 'similar_programs' => $similar_programs, 'check_user' => Auth::check()];
    }

    public function onRenderModerate()
    {
        $program = Program::withOutModerate()->withOrganizationModerate()->withFormatsModerate()
            ->find($this->property('id'));
        $this->page->title = $program->name;
        return ['program' => $program];
    }


    public function onRenderViewOrganizationList(){
        return ['programs' => $this->property('programs'), 'organization_id' => $this->property('organization_id')];
    }

    public function onGetCount($count = 0){
        $str = Lang::choice('программа|программы|программ', $count, [], 'ru');
        return "{$count} {$str}";

    }

    protected function queryOrganization($organization_id = null)
    {
        if (empty($organization_id))
            $organization_id = post('organization_id');

        return Program::withOutModerate()
            ->when($organization_id, function ($q) use ($organization_id) {
                return $q->byOrganization($organization_id);
            })
            ->allowed()
            ->with(['program_formats' => function ($query){
                return $query->withOutModerate();
            }]);
    }

    protected function cityFilter(){
        return Organization::byCity();
    }

    public function getProgramData($id)
    {
        return Program::find($id);
    }

}
