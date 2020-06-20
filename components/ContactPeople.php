<?php namespace Cds\Study\Components;

use Cds\Study\Components\ComponentBase;
use Cds\Study\Models\CdsFile;
use Cds\Study\Models\ContactPerson;
use Input;
use October\Rain\Support\Facades\Flash;
use App;

class ContactPeople extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'ContactPeople Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRenderLkEditOrganization()
    {
        $organization_id = $this->property('organization_id');
        $data = null;
        if(!empty($organization_id)) $data = $this->queryOrganization($organization_id)->getWithOutOriginal()->get();
        return ['data' => $data, 'organization_id' => $this->property('organization_id')];
    }

    public function onRenderModalLkEditElement($params)
    {
        $id = array_get($params, 'contact_id', null);
        $id = !empty($id) ? $id : null;
        $model = $this->queryOrganization()->findOrNew($id);

        if ( $orgId = data_get($params, 'organization_id') );
            $model->organization_id = $orgId;
        if(!empty($model->duplicate)) $model = $model->duplicate;
        $translate_days = $model::DAYS_TRANSLATE;
        $days = $model::DAYS;
        return ['model' => $model, 'translate_days' => $translate_days, 'days' => $days];
    }

    public function onUpdate(){
        $form_data = CdsFile::modifyDropZoneFormData(input(), ['avatar']);
        $avatar  = [];
        if(!empty($form_data['avatar'])) $avatar['avatar'] = $form_data['avatar'];
        if($id = post('contact_id')){
            $new_person = $this->queryOrganization()->find((int)$id);
            $new_person = $new_person->updateModelOrCreateDuplicate($avatar + input());
            $var = "~#contact_person_{$id}";
        }
        else {
            $new_person = ContactPerson::create($avatar + input());
            $var = "@#contacts_people_container";
        }
        Flash::success('Данные успешно сохранены');
        return $this->renderContactPerson([$new_person], $var);
    }

    public function renderContactPerson($contacts, $var){
        return [$var => $this->renderPartial('@_card_for_lk',
            [
                'contacts' => $contacts,
                'organization_id' => post('organization_id')
            ])
        ];
    }

    protected function renderContactPeopleList()
    {
        return ['#contact_people' => $this->renderPartial('@_lk_edit_organization',
            [
                'contacts' => $this->queryOrganization()->getWithOutOriginal()->get(),
                'organization_id' => post('organization_id')
            ])
        ];
    }

    public function onDelete()
    {
        $item = $this->queryOrganization()->find(post('contact_id'));
        if(!empty($item)){
            $item->deleteWithOriginalOrDuplicate();
            Flash::success('Контакт удален');
            $id = post('contact_id');
            return ["~#contact_person_{$id}" => ''];
        }
        Flash::success('Такого контакта не существует');
        return;
    }

    public function onRenderOrganizationView(){
        return ['contacts' => $this->property('contacts'), 'organization' => $this->property('organization')];
    }

    public function onAddMethodContact(){
        return ['@#contacts-links' => $this->renderPartial('@contact_link', [
            'code' => post('code', null)
        ])];
    }




    protected function queryOrganization($organization_id = null)
    {
        if (empty($organization_id))
            $organization_id = post('organization_id');

        return ContactPerson::withOutModerate()
            ->when($organization_id, function ($q) use ($organization_id) {
                return $q->byOrganization($organization_id);
            })
            ->allowed()
            ->with('avatar');
    }




}
