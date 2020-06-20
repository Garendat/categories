<?php namespace Cds\Study\Components;

use Cds\Study\Models\Organization;
use Cds\Study\Models\Program;
use Cds\Study\Models\UserAction;
use Cds\Study\Components\ComponentBase;
use RainLab\User\Facades\Auth;
use Cds\Study\Models\User;
use Flash;
use Db;
use Cds\Study\Models\Sector;
use Renatio\DynamicPDF\Classes\PDF;
use Config;
use Carbon\Carbon;
use Cds\Study\Models\ContactPerson;

class UserActions extends ComponentBase
{
    public $variant = 'favorite';

    public function componentDetails() {
        return [
            'name'        => 'UserActions Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties() {
        return [
            'action' => ['default' => 'favorite'],
        ];
    }

    // вывод списка моиего избранного в ЛК
    protected function onRenderLkList() {
        return $orgs = Organization::query()
            ->hasMyFavorite()
            ->orWhereHas('programs', function ($pgQ) {
                $pgQ->hasMyFavorite();
            })
            ->with(['programs' => function ($pgQ) {
                return $pgQ
                    ->hasMyFavorite()
                    ->with('program_formats');
            }])
            ->paginate()
        ;
    }

    public function onRenderFavorite($params) {
        $this->addComponent('Programs', 'Programs', []);

        $action = 'favorite';
        $object = $this->property('object');
        if ( empty($object) ) {
            $object['id'] = $this->property('object_id');
            $object['name'] = $this->property('object_name', 'org');
        }
        $act = UserAction::my()->action($action)->byObject($object)->first();
        if (empty($act)) {
            $newData = [
                'action' => $action,
                'object_type' => UserAction::CLASSES[$object['name']],
                'object_id' => $object['id'],
            ];
            $act = (new UserAction)->fill($newData);
        }
        return $act;
    }

    public function onToggle() {
        $action = post('action');
        $object = post('object', [
            'name' => post('object_name'),
            'id' => post('object_id'),
        ]);

        $data = [
            'action' => $action,
            'object_name' => $object['name'],
            'object_id' => $object['id'],
        ];


        $act = UserAction::my()->action($action)->byObject($object)->makeToggle($data);
        $updateElement = "~#{$action}-{$act->obj['name']}-{$act->obj['id']}";
        //dd($updateElement);
        return [$updateElement => $this->renderPartial("@_{$act->action}", ['act' => $act])];
    }

    public function onRenderStatistics() {
        $organizations = $this->getMyOrganizations()->get();
        $ids = $organizations->pluck('id');
        $result = $this->getStatisticsByOrganizations($ids);

        return ['data' => $result, 'organizations' => $organizations->pluck('name', 'id')];
    }

    public function onGetPrograms() {
        if(empty(post('organization', null))){
            throw new \AjaxException([
                'type' => 'error',
                'message' => 'Выберите организацию',
                'error' => 'Требуется выбрать организацию',
            ]);
        }
        return Program::where('organization_id', post('organization'))
            ->searchByPhrase(post('program'))
            ->notFictive()->select('id', 'name')->get()
            ->prepend(['id' => '', 'name' => 'Все программы'])
            ->toArray();
    }

    public function onGetStatistics() {
        return ['#kuzon-table' => $this->renderPartial('@statistic_table', ['data' => $this->getDataStatistics()])];
    }

    public function onGetPdfStatistics() {
        return $this->getPdfBill();
    }

    protected function getDataStatistics() {
        $start = '';
        $end = '';
        if(!empty(post('start'))) $start = Carbon::createFromFormat('d.m.Y', post('start', ''));
        if(!empty(post('end'))) $end = Carbon::createFromFormat('d.m.Y', post('end', ''));
        if(!empty(post('program', null))){
            $result = $this->getStatisticsByProgram(post('program'), $start, $end);
        }
        elseif(!empty(post('organization', null))){
            $result = $this->getStatisticsByOrganizations([post('organization')], $start, $end);
        }
        else{
            $result = $organizations = $this->getMyOrganizations()->get();
            $ids = $organizations->pluck('id');
            $result = $this->getStatisticsByOrganizations($ids, $start, $end);
        }
        return $result;
    }


    protected function getStatisticsByOrganizations($ids = [], $start = '', $end = '') {
        return UserAction::getStatisticsByOrganizations($ids, $start, $end);
    }

    protected function getStatisticsByProgram($program_id = null, $start = '', $end = '') {
        $program = Program::find($program_id);
        $actions = UserAction::byPrograms([$program_id])->groupBy('action');
        if(!empty($start)) $actions = $actions->whereDate('updated_at', '>=', $start);
        if(!empty($end)) $actions = $actions->whereDate('updated_at', '<=', $end);
        $actions = $actions->addSelect(Db::raw('action, count(*) as count'))->pluck('count', 'action');
        $row = ['Посещения', $actions->get('view', 0)];
        if(!empty($program->sector->name)) $row[] = $program->sector->name;
        $result = [ $row ];
        foreach(UserAction::STATISTICS as $key => $event){
            $result[] = [$event, $actions->get($key, 0)];
        }
        return $result;
    }

    protected function getMyOrganizations() {
        return Organization::my()->hasServices(null, ['send_statistics']);
    }

    private function getPdfBill() {
        $templateCode = 'cds.study::pdf.statistics'; // unique code of the template
        $start = post('start', null);
        $end = post('end', null);
        $user = Auth::getUser();
        $localPath = "public/";
        $object = null;
        $variant = null;
        if(post('program', null)){
            $object = Program::find(post('program'));
            $variant = 'program';
        }

        elseif(post('organization', null)){
            $variant = 'organization';
            $object = Organization::find(post('organization'));
        }
        $uploadsPath =  storage_path("app/uploads/{$localPath}");

        $pdf_file_name =  $this->getPdfFileName($user, $object, $start, $end, $uploadsPath);
        $pdf_file_name_directory =  $uploadsPath . $pdf_file_name;
        PDF::loadTemplate($templateCode, [
            'data' => $this->getDataStatistics(),
            'start' => $start,
            'end' => $end,
            'object' => $object,
            'variant' => $variant
        ])->setPaper('a4', 'portrait')->save($pdf_file_name_directory);

        return $baseUrl = url(Config::get('cms.storage.uploads.path')) . "/" . $localPath . $pdf_file_name;
    }

    private function getPdfFileName($user, $object, $start, $end, $uploadsPath){
        $name =  mb_strtolower(str_random(12) . '.pdf');
        $mask_names = [];
        if(!empty($start) && !empty($end)) $mask_names[] = "{$start}-{$end}";
        if(!empty($user)) $mask_names[] = $user->id;
        if(!empty($object)){
            if($object instanceof Program) $mask_names[] = "program";
            else $mask_names[] = "organization";
            $mask_names[] = $object->id;
        }
        else {
            $mask_names[] = 'all';
        }
        $mask_names = join("-", $mask_names);
        foreach (glob("$uploadsPath/$mask_names-*.pdf") as $old_file) {
            unlink($old_file);
        }
        return "{$mask_names}-{$name}";

    }

    public function onGetInfoOrganization(){
        $organization = Organization::withOutModerate()->find(post('organization_id'));
        $variant = post('variant');
        if($variant == 'promocode'){
            $organization->promocode = $organization->getOriginalModel()->get_promocode;
        }
        UserAction::createActionOnOrganization($organization->id, $variant);
        return ["~#{$variant}" => $this->renderPartial('@info_organization', ['organization' => $organization, 'variant' => $variant])];
    }

    public function onGetInfoContact(){
        $contact = ContactPerson::withOutModerate()->find(post('contact_id'));
        $variant = post('variant');
        UserAction::createActionOnOrganization($contact->organization_id, $variant);
        return ["~#{$variant}" => $this->renderPartial('@info_contact', ['contact' => $contact, 'variant' => $variant])];
    }
}
