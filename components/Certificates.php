<?php namespace Cds\Study\Components;

use Cds\Study\Components\ComponentBase;
use Cds\Study\Models\CdsFile;
use Cds\Study\Models\Certificate;
use Cds\Study\Models\Organization;
use Input;
use Flash;

class Certificates extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Certificates Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {
        $this->addCss("/plugins/cds/study/components/certificates/assets/certificates.css");
    }

    public function onRenderEditOrganization()
    {
        $organization_id = $this->property('organization_id');
        $data = null;
        if(empty($organization_id)) return;
        $data = $this->queryOrganization($organization_id)->get();
        return ['data' => $data, 'organization_id' => $organization_id];
    }

    // В связи с введением тарифов устарел. Пока оставим
    public function onRenderView()
    {
        return $this->property('certificates');
    }

    public function onGetListByOrganization(){
        return [
            '#certificates' => $this->renderPartial('@_view', [
                'certificates' => Certificate::where('organization_id', post('organization_id'))->get()
            ])
        ];
    }

    public function onGetListByOrganizationModerate(){
        return [
            '#certificates' => $this->renderPartial('@_view', [
                'certificates' => $this->queryOrganization()->get()
            ])
        ];
    }

    public function onRenderModalEditElement($params)
    {
        $id = array_get($params, 'certificate_id', null);
        $id = empty($id) ? null : $id;
        $model = $this->queryOrganization()->findOrNew($id);
        $model->organization_id = $params['organization_id'];
        return ['data' => $model, 'organization_id' => $params['organization_id']];
    }

    public function onUpdate()
    {
        $form_data = CdsFile::modifyDropZoneFormData(input(), ['image']);
        $image = [];
        if(!empty($form_data['image']))
            $image['image'] = $form_data['image'];
        if($id = post('certificate_id'))
        {
            $new_certificate = $this->queryOrganization()->find($id);
            $new_certificate = $new_certificate->updateModelOrCreateDuplicate($image + input());
            $var = "~#certificate_{$id}";
        }
        else {
            $new_certificate = Certificate::create($image + input());
            $var = "@#certificates_container";
        }

        Flash::success('Данные успешно сохранены');
        return $this->renderCertificateLk([$new_certificate], $var);
    }

    protected function renderCertificateLk($certificates, $var){
        return [$var => $this->renderPartial('@_lk_card', ['certificates' => $certificates])];
    }

    protected function renderCertificatesList()
    {
           return ['~#certificates' => $this->renderPartial('@_edit_organization',
                [
                'certificates' => $this->queryOrganization()->get(),
                'organization_id' => post('organization_id')
                ])
            ];
    }

    public function onDelete()
    {
        $id = post('certificate_id');
        $item = $this->queryOrganization()->find($id);
        if($item){
            $item->deleteWithOriginalOrDuplicate();
            Flash::success('Сертификат удален');
            return ["~#certificate_{$id}" => ''];
        }
        Flash::success('Сертификата не найдено');
        return;
    }
    protected function queryOrganization($organization_id = null)
    {
        if(empty($organization_id)) $organization_id = post('organization_id');
        return Certificate::withOutModerate()
            ->when($organization_id, function($query) use ($organization_id){
                $query->byOrganization($organization_id);
        })
            ->allowed()
            ->with('image')
            ->getWithOutOriginal();
    }
}
