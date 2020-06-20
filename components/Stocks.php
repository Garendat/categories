<?php namespace Cds\Study\Components;

use Cds\Study\Components\ComponentBase;
use Cds\Study\Models\CdsFile;
use Flash;
use Cds\Study\Models\Stock;
use Input;

class Stocks extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Stocks Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRenderEditOrganization()
    {
        $organization_id = $this->property('organization_id');
        $data = null;
        if(!empty($organization_id)) $data = $this->queryOrganization($organization_id)->get();
        return ['data' => $data, 'organization_id' => $organization_id];
    }

    public function onRenderModalEditElement($params)
    {
        $id = array_get($params, 'stock_id', null);
        $id = !empty($id) ? $id : null;
        $model = $this->queryOrganization($params['organization_id'])->findOrNew($id);
        $model->organization_id = $params['organization_id'];
        return ['data' => $model, 'organization_id' => $params['organization_id']];
    }

    public function onRenderViewOrganization()
    {
        return $this->property('stocks');
    }

    public function onUpdate()
    {
        $form_data = CdsFile::modifyDropZoneFormData(input(), 'image');
        $image = [];
        if(!empty($form_data['image'])) $image['image'] = $form_data['image'];


        if($id = post('stock_id')) {
            $new_stock = $this->queryOrganization()->with('image')->find($id);
            $new_stock = $new_stock->updateModelOrCreateDuplicate($image + input());
            $var = "~#stock_{$id}";
        }
        else {
            $new_stock = Stock::create($image + input());
            $var = "@#stocks_container";
        }


        Flash::success('Данные успешно сохранены');
        return $this->renderStockLk([$new_stock], $var);
    }

    public function onDelete()
    {
        $id = post('stock_id');
        $item = $this->queryOrganization()->find($id);
        if(!empty($item)){
            $item->deleteWithOriginalOrDuplicate();
            Flash::success('Акция удалена');
            return ["~#stock_{$id}" => ''];
        }
        Flash::success('Такой акции не существует');
        return;


    }

    protected function renderStockLk($stocks, $var){
        return [$var => $this->renderPartial('@_lk_card', ['stocks' => $stocks])];
    }

    protected function renderStocksList()
    {
        return ['#stocks' => $this->renderPartial('@_edit_organization',
            [
                'stocks' => $this->queryOrganization()->orderBy('order', 'asc')->get(),
                'organization_id' => post('organization_id')
            ])
        ];
    }


    protected function queryOrganization($organization_id = null)
    {
        if (empty($organization_id))
            $organization_id = post('organization_id');

        return Stock::when($organization_id, function ($q) use ($organization_id) {
                return $q->byOrganization($organization_id);
            })
            ->allowed()
            ->getWithOutOriginal()
            ->with('image');
    }
}
