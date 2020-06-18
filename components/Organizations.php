<?php namespace Cds\Study\Components;


use Cds\Study\Components\ComponentBase;
use Cds\Study\Models\ObjectTypeAllow;
use Cds\Study\Models\ObjectValue;
use Cds\Study\Models\Organization;
use Cds\Study\Models\Promocode;
use Cds\Study\Models\UserAction;
use Cds\Study\Models\CdsFile;
use System\Models\File;
use October\Rain\Support\Facades\Flash;
use RainLab\User\Facades\Auth;
use Cds\Study\Models\Program;
use Input;
use Response;
use Redirect;
use ApplicationException;
use BackendAuth;
use App;
use Session;


class Organizations extends ComponentBase
{
    public $variant = 'list';
    public $hasSendEmail = null;

    public function componentDetails()
    {
        return [
            'name'        => 'Organizations Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function init() {
        $this->addComponent('Events', 'Events', []);
        $this->addComponent('Programs', 'Programs', $this->properties);
        $this->addComponent('Certificates', 'Certificates', []);
        $this->addComponent('Stocks', 'Stocks', []);
        $this->addComponent('ContactPeople', 'ContactPeople', []);
    }

    public function onRun(){

        if(!empty($this->property('moderate'))){
            if(!BackendAuth::check()) return $this->controller->run('404');
        }

        $mode = $this->property('mode');
        if ($mode == 'organization_detail' && $id = (int) $this->controller->param('id')) {
            $data = Organization::find($id);
            if (empty($data)) return Response::make($this->controller->run('404')->getContent(), 404);
        }
    }


//============================== Render Main Page =========================================================================//

    public function onRenderList()
    {
        $orgs = $this->queryLkBuilder()->getWithOutOriginal()->with(['original' => function($q){
            return $q->with(['programs' => function($q){
                return $q->getWithOutOriginal()->notFictive()->with(['rates', 'program_formats' => function($query){
                    return $query->getWithOutOriginal();
                }]);
            }]);
        }, 'rates', 'views'])->with(['programs'=> function($query){
            $query->getWithOutOriginal()->notFictive()->with(['rates', 'program_formats' => function($query){
                return $query->getWithOutOriginal();
            }]);
        }, 'rates', 'views'])->paginate(5);

        $orgs->changed_id = Session::pull('org_id', null);
        return $orgs;
    }

    public function onRenderView()
    {
        $organization = Organization::
            //withActiveServicesSlugs(['promo_event_placing', 'extend_contacts', 'logo_photo', 'send_email', 'addinfo_search', 'scan_copy'])
            with('active_services')
            ->with(['my_rate', 'rates', 'views', 'general_contact_person', 'general_contact_person.avatar'])->withStocks()->withEvents()
            ->withSimplePrograms()->find($this->property('id'));
        if(!empty($organization)) $organization->createView();
        $stocks_and_events = $organization->stocks->merge($organization->events)->shuffle();

        $sectors_id = Program::where('organization_id', $organization->id)->lists('sector_id');

        $organizations_same = $this->cityFilter()->where('id', '<>', $organization->id)->with(['my_favorite', 'my_rate', 'rates', 'views'])->
        whereHas('programs', function($query) use ($sectors_id){
            $query->whereIn('sector_id', $sectors_id);
        })->inRandomOrder()->limit(3)->get();

        $this->page->title = $organization->name;

        return ['organization'=> $organization, 'organizations_same' => $organizations_same, 'stocks_and_events' => $stocks_and_events];
    }

    public function onRenderModerate()
    {
        $organization = Organization::withOutModerate()->with('original')->withModerateStocks()->withModerateEvents()->withModerateEvents()->withModerateContactPeople()->find($this->property('id'));
        $original_organization = $organization->getOriginalModel()->load(['rates', 'views', 'general_contact_person', 'general_contact_person.avatar'])->load([
            'programs' => function($query) {
                return $query->notFictive()->whereHas('program_formats', function ($query) {
                    return $query->notFictive();
                })->with(['program_formats' => function ($query) {
                    return $query->notFictive();
                }]);
            }]);
        $stocks_and_events = $organization->stocks->merge($organization->events)->shuffle();

        return ['organization'=> $organization, 'stocks_and_events' => $stocks_and_events, 'original_organization' => $original_organization];
    }

    public function onRenderEdit($renderParams = [])
    {
        $params = ObjectTypeAllow::getParamsByClass(Organization::class);
        $params_ids = ObjectTypeAllow::getParamsByClass(Program::class)->pluck('id')->toArray();
        $params = $params->toArray();
        $exists_parameters = [];

        $organization = $this->queryLkBuilder()->findOrNew($this->param('id', 0));

        $organization->load(['promocode_user', 'promocode_guest', 'parameters']);
        foreach($organization->parameters as $parameter) {
            if (!empty($parameter->parameter_id) && in_array($parameter->parameter_id, $params_ids))
                $exists_parameters[$parameter->parameter_id] = $parameter;
        }

        if (!empty($inn = array_get($renderParams, 'inn')))
            $organization->inn = $inn;

        if(empty($organization->promocode_user)){
            $promocodes = Promocode::generatePromocodes();
        } else {
            $promocodes = [
                'guest' => $organization->promocode_guest,
                'user' => $organization->promocode_user,
            ];
        }
        // все необходимые данные мы подтянули из основной модели, дальше подменяем ее дубликатом, если он есть,
        // работать будет всегда по скольку айдишник будет приходить всегда оригинала
        $organization = $organization->getOriginalOrDuplicate();
        return ['organization' => $organization, 'object_options' => $params, 'promocodes' => $promocodes, 'exists_parameters' => $exists_parameters];
    }

    public function onRenderRequestWithOrgLists()
    {
        return Auth::getUser()->organization_requests()->get();
    }

    public function onDeleteRequest(){
        $action_id = post('action_id');
        $action = UserAction::find($action_id);
        $action->delete();
        return ["#action_{$action_id}" => ''];
    }

    public function onRestoreRequest(){
        $action_id = post('action_id');
        $action = UserAction::find($action_id);
        $action->update(['value' => 1]);
        return ;
    }

//!!!!!!!!!!!!!!!!!!!!!!!!!!!= Render Main Pages =!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


//============================== Render Partials =========================================================================//

    protected function renderImageGallery($organization)
    {
        return ['#images' => $this->renderPartial('@_lk_image_gallery', [
            'images' => $organization->images,
            'organization_id' => $organization->id
        ])];
    }

    public function onGetListGalleryView(){
        $org = Organization::with('images')->find(post('organization_id'));
        $gallery = [];
        if (!empty($org)) $gallery = $org->images;
        return ['#gallery' => $this->renderPartial('@_view_gallery', ['images' => $gallery])];
    }

    public function onGetListGalleryModerate(){
        $org = Organization::withOutModerate()->with('images')->find(post('organization_id'));
        $gallery = [];
        if (!empty($org)) $gallery = $org->images;
        return ['#gallery' => $this->renderPartial('@_view_gallery', ['images' => $gallery])];
    }

    public function onRenderBranches($orgs = [])
    {
        $count = !empty($orgs) ? count($orgs) : 0;
        return [
            "#branches" => $this->renderPartial('@_branches', ['branches' => $orgs, 'inn' => post('search_inn')]),
            '#add_branches' => $this->renderPartial('@add_branche_link', ['inn' => post('search_inn')]),
            '~#count_org' => $this->renderPartial('@count_org', ['count' => $count]),
            'count_org' => $count,
        ];
    }

    public function onRenderSearchResult()
    {
        return [
            'data' => $this->property('organizations'),
            'count_programs' => $this->property('count_programs'),
            'regions' => $this->property('regions'),
        ];
    }

    public function onRenderResultSimilar()
    {
        return ['organizations' => $this->property('organizations')];
    }
    public function onRenderRequest()
    {
        return $this->queryLkBuilder()->get()->toArray();
    }

//!!!!!!!!!!!!!!!!!!!!!!!!!!!= Render Partials =!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



//============================== Render Modals =========================================================================//

    public function onRenderModalRequest($params)
    {
        $id = array_get($params, 'org', null);
        $org = Organization::find($id);
        return $org;
    }

    public function onRenderModalAddImage($params)
    {
        return $params['organization_id'];
    }

    public function onRenderModalDeleteBranch()
    {
        $organization = $this->queryLkBuilder()->find(post('id'))->toArray();
        return $organization;
    }

    public function onRenderModalInfo($params)
    {
        $id = array_get($params, 'modal-params.id', null);
        $org = $this->queryLkBuilder()->findOrNew($id);
        $org->throwOnValidation = false;
        $org->fill($params);

        return $org;
    }

    public function onRequest()
    {
        UserAction::UpdateOrCreateRequest(['object_id' => post('organization_id')], array_except(post(), 'id'));
        Flash::success("Заявка успешно сохранена");
        return ['~#my_requests' => $this->renderPartial('@request_org_cards', ['orgs' => $this->onRenderRequestWithOrgLists()])];
    }
// !!!!!!!!!!!!!!!!!!!!!!!!!!!= Render Modals =!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


//============================== Ajax methods =========================================================================//

    public function onResetBranch(){
        //ResetDuplicate
        $organization = $this->queryLkBuilder()->find(post('id'));
        if(!empty($organization)){
            $res = $organization->resetDuplicate();
            if(!empty($res)) return Redirect::to($res->edit_link);
        }
    }

    public function onAddBranch(){
        $org = $this->queryLkBuilder()->findOrNew( (int) post('id'));
        $logo = [];
        $price = [];

        $form_data = CdsFile::modifyDropZoneFormData(input(), ['images', 'logotype', 'price']);

        if (!empty($form_data['logotype'])) {
            $logo  = ['logotype' => $form_data['logotype']];
        }
        if (!empty($form_data['price'])) {
            $price  = ['price' => $form_data['price']];
        }

        $org->updateModelOrCreateDuplicate( $price + $logo + $form_data + ['user_id' => Auth::id()]);
        // это Димина реализация, она имеет смысл только при создании модели, хотя может уже и не имеет вовсе, надо тестить
        // связь events добавлена в fillable модели Организации - заполняется из поста сама

        $org->updatePromocodes(post('promocodes'));

        $options = post('options', []);
        if(!empty($options))
            ObjectValue::createWithModel($options, Organization::class, $org->getOriginalId());

        if(post('id')) {
            Flash::success('Изменения успешно сохранены');
            return Redirect::to('/lk/organizations');
        } else {
            Flash::success('Организация успешно создана');
            return Redirect::to('/lk/organizations');
        }
    }

    public function onGetBranches() {
        $orgs = null;
        if (str_replace(' ', '', post('search_inn')) != ''){
            $user = Auth::getUser();
            $orgs_exists = UserAction::where('action', $user->id)->lists('id');
            $orgs = Organization::whereNotIn('id', $orgs_exists)->byInn(post('search_inn'))->notInUser()->take(5)->get();
        }
        return $this->onRenderBranches($orgs);
    }


    public function onDeleteBranch() {
        $organization = $this->queryLkBuilder()->with('programs')->find(post('id'));
        if(empty($organization)){
            Flash::success('Филиала не найдено');
            return;
        }
        if(mb_strtolower(post('confirm')) !== 'подтверждаю')
            throw new ApplicationException('Не правильно подтверждено удаление');
        $organization->deleteWithOriginalOrDuplicate();
        Flash::success('Филиал успешно удален');
        return Redirect::to('/lk/organizations');
    }



    public function onDeleteImage(){
        $id = post('id');
        $organization = $this->queryLkBuilder()->with('images')->find(post('organization_id'));
        if(empty($organization)){
            Flash::error("Организации не существует либо вы не являетесь владельцем");
            return;
        }
        $file = $organization->images()->find(post('id'));
        if (empty($file)){
            Flash::error("Файла не существует");
            return;
        }
        $message = $file->is_video ? 'Видео удалено' : 'Фото удалено';
        $file->delete();
        Flash::success($message);
        return ["~#image_{$id}" => ''];
    }

//!!!!!!!!!!!!!!!!!!!!!!!!!!!= Ajax methods =!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//

    protected function notFoundCheck($model){
        if(empty($model)) return $this->controller->run('404');
    }

    protected function queryLkBuilder()
    {
        return Organization::withOutModerate()->my();
    }

    protected function cityFilter(){
        return Organization::byCity();
    }

    public function isShowServiceButton($id)
    {
        return Organization::find($id);
    }


    // Добавление видео и картинки до лучших времен
//    public function onAddImage(){
//        $includeFileMessage = [];
//
////        $organization = $this->queryLkBuilder()->with('images')->find(post('organization_id'));
//        $image = null;
//        $video = null;
//        if(Input::hasFile('image')) {
//            $image = new CdsFile();
//            $image->create(['data' => Input::file('image')]);
//            $includeFileMessage[] = 'Фото';
//        }
//
//        if (!empty(post('url'))) {
//            $data = post();
//            $video = CdsFile::make(array_except($data, ['organization_id']));
//            $video->fromUrlVideo($data['url']);
//            $video->is_public = true;
//            $video->save();
//
//            $includeFileMessage[] = 'Видео';
//        }
//
//        if (Input::hasFile('image') || !empty(post('url'))) {
//            $includeFileMessage = implode(" и ", $includeFileMessage);
//            Flash::success("{$includeFileMessage} успешно добавлены");
//            $result = null;
//            if(Input::hasFile('image')) $result = $this->renderPartial('@lk_image_card', ['image' => $image]);
//            elseif(!empty(post('url'))) $result = $this->renderPartial('@lk_image_card', ['image' => $video]);
//            return ['@#image_gallery_list' => $result];
//        }
//
//        Flash::error('Добавьте фото');
//        return;
//    }
}
