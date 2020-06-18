<?php namespace Cds\Study\Components;


use Cds\Study\Models\Organization;
use Cds\Study\Models\Program;
use Cds\Study\Models\Sector;
use Cds\Study\Components\ComponentBase;
use Illuminate\Support\Facades\DB;
use October\Rain\Support\Facades\Flash;

class Sectors extends ComponentBase
{

    public $category = null;
    public $categories_top = null;
    public $parents = null;
    public $ids = null;
    public $slug = null;

    public function componentDetails()
    {
        return [
            'name'        => 'Sectors Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function onRun()
    {

    }


    protected function onRenderDefault()
    {

    }

    protected function onRenderTopMenu()
    {
        $this->getRootCategories();
    }

    protected function onRenderLeftMenu()
    {
        if($this->slug = $this->property('slug'))
        {
            $category = $this->onGetCategoryBySlug();
            if(!empty($category))
            {
                $result = $category->getAllParentsWithChildren();
                $this->parents = array_pop($result);
                $this->ids = array_pop($result);
            }
        }
        $this->getRootCategories();
    }

    protected function getRootCategories()
    {
        $this->categories_top = Sector::getRootCategories()->toArray();
    }

    public function onRenderRootCategoriesView(){
        $categories_all_root= Sector::with('counter')->isPopular()->orderBy('sort_order')->get();
        $categories_popular = Sector::isGeneral()->whereHas('counter', function($query){
            $query->whereNotNull('org_count')->where('org_count', '>', 0);
        })->with('counter')->with('general_image')->limit(5)->get()->each(function($model) {
            $all_children = $model->getChildrenInlineTree();
            $organizations = $this->cityFilter()->whereHas('programs', function($query) use($all_children){
                $query->whereIn('sector_id', $all_children->pluck('child'))->whereHas('program_formats');
            })->inRandomOrder()->limit(3)->get();
            $model->organizations = $organizations;
        });
        return ['categories_popular' => $categories_popular, 'categories_all_root' => $categories_all_root];
    }
    // рендер нижнего футера категорий, там 2 колонки соответственно бьем на 2 массива
    public function onRenderFooter(){
        $categories_all_root = Sector::with('counter')->whereLvl(1)->orderBy('sort_order')->limit(33)->get();
        if(!empty($categories_all_root)){
            $first_column = $categories_all_root->splice(0, 16);
            return ['first_column' => $first_column->toArray(), 'second_column' => $categories_all_root->toArray()];
        }

    }

    public function onRenderChildren(){

        $this->slug = post('slug');
        $category = $this->onGetCategoryBySlug();
        if(!empty($category)){
            if($category->getChildCount()){
                return ["#category_{$this->slug}" => $this->renderPartial('@children', [
                    'index' => post('index'),
                    'children' => $category->getChildren()->toArray(),
                    'ids' => []
                ])];
            }
            Flash::error('Не реализовано дальше');
            return;
        }
        Flash::error("Категории нет)");
        return;
    }

    public function onRenderSeeMore(){
        return Sector::isPopular()->inRandomOrder()->get();
    }

    protected function onGetCategoryBySlug(){
        return Sector::getCategoryBySlug($this->slug);
    }

    protected function cityFilter(){
        return Organization::byCity();
    }
}
