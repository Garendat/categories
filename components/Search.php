<?php namespace Cds\Study\Components;

use Carbon\Carbon;
use Cds\Study\Components\ComponentBase;
use Cds\Study\Models\Program;
use Cds\Study\Models\Sector;
use Cds\Study\Models\Organization;
use Cds\Study\Models\Setting;
use Db;
use Session;
use Redirect;
use Response;
use Illuminate\Support\Facades\Lang;
use Cds\Study\Models\Service;
use Cds\Study\Models\ProgramFormat;
use Cds\Study\Models\SearchRequest;


// ГОША ПРИВЕТ!!!!

class Search extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Search Component',
            'description' => 'No description provided yet...'
        ];
    }

    public function defineProperties()
    {
        return [];
    }

    public function init(){
        $this->addComponent('Programs', 'Programs', []);
        $this->addComponent('Organizations', 'Organizations', []);
    }

    public function onRun()
    {
        $mode = $this->controller->getPage()->settings['url'];
        if ($mode == '/education/category/:slug' && $slug = $this->controller->param('slug')) {
            //dd('aaaaaa');
            $data = Sector::getCategoryBySlugAndId($slug);
            if (empty($data)) return Response::make($this->controller->run('404')->getContent(), 404);
        } else  { // поисковый запрос
            $this->newRequest();
            $exist = null;
            if (!empty(post('p')) && count(post('p')) == 1) {
                $exist = Sector::whereIn('name', post('p', []))->get();
            } else {
                $exist = Sector::whereIn('id', post('c', []))->get();
            }

            if (count($exist) === 1) { // ровно одна категория
                // перенаправляем на ее страницу
                return Redirect::to($exist->first()->searchLink, 301);
            }
        }
    }

    public function onRenderDefault(){
        return;
    }

    public function onSearchText($count = 10){
        $categories = $this->getCategoriesByPhrase(post('text'))
            ->take(post('count', $count))->select('id', 'name', 'slug')->get();
        $categories = $categories->merge($this->getOrganizationsByPhrase($count))->toArray();
        return $categories;
    }

    protected function getOrganizationsByPhrase($count = 10){
        return $this->cityFilter()->searchByPhrase(post('text'))->select('id', 'name')->take($count)->get();
    }

    public function onSearchPhrase(){
        $categories = $this->getCategoriesByPhrase(post('phrase'))->get();
        $lvl = post('lvl', 2);
        $categories_by_lvl = [];
        foreach ($categories as $category){
            $categories_by_lvl = array_merge($categories_by_lvl, $category->getFindTreeToLvl($lvl));
        }
        return array_unique($categories_by_lvl);
    }

    public function onRenderModalSearch(){
        $categories = Sector::with('counter')->getRootCategories()->whereHas('children')->with(['children' => function($q){
            return $q->with('counter');
        }])->get();
        return ['categories' => $categories, 'get_phrases' => get('p', []), 'get_categories' => get('c', [])];
    }

    protected function newRequest(){
        if(!empty(get('new'))){
            $this->deleteParamsInSession();
        }
    }

    public function onRenderResult($count = 10){
        $this->newRequest();
        Session::pull('c');
        $exist = Sector::whereIn('id', post('c', []))->with('counter')->get();

        $seo_text = [];

        if($this->property('sector')){
            $categories = Session::get('c');
            if(!empty($categories)){
                $sector = Sector::find($categories[0]);
                if(!empty($sector)){
                    if(!empty($sector->seo_text)){
                        $seo_text = $sector->seo_text;
                    }
                }
            }
        }

        if (empty($seo_text)) $seo_text = Setting::getSeoTextResult();

        return ['data' => $this->onFilter($count), 'exist' => $exist, 'seo_text' => $seo_text];
    }

    public function onRenderCategory($params = []) {
        $this->partial = '_result';

        $count = data_get($params, 'count', 10);
        $categories = Sector::getCategoryBySlugAndId(data_get($params, 'slug'));
        if (!empty($categories)) $this->page->title = $categories->name;
        if(Session::get('c', null) != $categories->id){
            $this->deleteParamsInSession();
            Session::put('c', [$categories->id]);
        }
        $children = $categories->children()->with('counter')->get();

        $seo_text = '';
        if(!empty($categories)){
            if(!empty($categories->seo_text)){
                $seo_text = $categories->seo_text;
            }
        }

        if (empty($seo_text)) $seo_text = Setting::getSeoTextResult();

        return ['data' => $this->onFilter($count), 'children' => $children, 'seo_text' => $seo_text];
    }

    protected function onGetCategoryLevelFilter($lvl = null){
        return Sector::getCategoriesByLevel($lvl);
    }

    protected function getCategoriesByPhrase($text){
        return Sector::byPhrase($text);
    }

    protected function getPhrases($name = 'name'){
        $result = '';
        $phrases = get('p', []);
        foreach($phrases as $key => $item){
            if($key == 0) $result .= "{$name} ILIKE '%{$item}%'";
            else $result .= " OR {$name} ILIKE '%{$item}%'";
        }
        return $result ? "{$result}" : '';
    }

    protected function onsetSession(){
        Session::put('parameters.org_params', array_keys(post('org_params', [])));
        Session::put('parameters.program_params', array_keys(post('program_params', [])));
        Session::put('parameters.min_term', post('min_term'));
        Session::put('parameters.max_term', post('max_term'));
        Session::put('parameters.min_price', post('min_price'));
        Session::put('parameters.max_price', post('max_price'));
    }

    protected function deleteParamsInSession(){
        Session::pull('parameters');
    }

    public function onDeleteSessionParams(){
        $this->deleteParamsInSession();

        return $this->onGetUpdate();

    }

    public function onOn(){
        $this->onsetSession();
        return $this->onGetUpdate();
    }

    public function onGetUpdate($count=10){

        $response = $this->onFilter(10);
        $count = $response['count_org'];
        $count_options = $response['count_options'];
        $regions = $response['regions'];
        return [
            '#count_org' => "Найдено {$count} шт.",
            '#count_options' => "Фильтр ({$count_options}) выбрано",
            '#regions' => $regions,
            '#search-result' => $this->renderPartial('@_organizations_block.htm', ['data' => $response]),
            '~#paginator' => $this->renderPartial('pagination/pagination', ['data' => $response['organizations']])
        ];
    }

    protected function onFilter($count=10){
        $parent_ids = get('c', []) ?: Session::get('c', []);
        $parameters = Session::get('parameters');
        // получаем категории
        $result_category_ids = Sector::getCategoriesByIdAndPhrases($this->getPhrases(), $parent_ids);
        $today = Carbon::today();
        $result = $this->cityFilter();

        // Берутся одним запросом тарифы которые нужны для сортировки
        $services = Service::whereIn('slug', ['top_one', 'top_twothree', 'addinfo_search'])->pluck('id', 'slug');
        //Если пришли опции для фильтра то проверяет проплачен ли тариф у организации
        if(!empty($parameters['org_params']) || !empty($parameters['program_params'])){
            $result = $result->whereHas('services', function($query) use($services, $today){
                $query->where('service_id', $services->get('addinfo_search'))
                        ->whereDate('date_start', '<=', $today)
                        ->whereDate('date_end', '>=', $today);
            });
        }

        if(!empty($org_params)) $result = $result->parameters($org_params);

        $result = $result->where(function($query) use($result_category_ids, $parameters){
            $query->wherePhrases($this->getPhrases('cds_study_organizations.name'))
                ->OrWhereHas('programs', function($query) use($result_category_ids, $parameters){
                    $query->whereIn('sector_id', $result_category_ids)->whereHas('program_formats', function($query) use($parameters){
                        return $query->filter($parameters);
                    });
                    if(!empty($parameters['program_params'])) $query->parameters($parameters['program_params']);
                });
        });

        $limits = ProgramFormat::getLimits(['ids' => $result_category_ids, 'phrases' => $this->getPhrases('cds_study_organizations.name')]);

        $count_org = $result->count();

        SearchRequest::log([
            'p' => get('p', []),
            'c' => $parent_ids,
            'realm_id' => array_first(array_wrap(Session::get('realm'))),
            'r' => $count_org,
        ]);

        $regions = $this->onGetRegions(count($parent_ids));
        $result = $result->leftJoin('cds_study_object_services as os', function($join) use($today, $services){
            $join->on('cds_study_organizations.id', '=', 'os.object_id')
                ->where('os.object_type', '=', Organization::class)
                ->where(function($query) use($services){
                    $query->where('os.service_id', '=', $services->get('top_one'))->orWhere('os.service_id', '=', $services->get('top_twothree'));
                })
                ->where('os.date_start', '<=',$today)
                ->where('os.date_end', '>', $today)
            ;})
            ->leftJoin('cds_study_programs as p', function($join) use($result_category_ids){
                $join->on('p.organization_id', '=', 'cds_study_organizations.id')
                    ->whereIn('p.sector_id', $result_category_ids)
                    ->whereNull('p.original_id')
                    ->where('p.status', 2);
            })
            ->leftJoin('cds_study_object_services as os_p', function($join) use($today){
                $join->on('os_p.object_id', '=', 'p.id')
                    ->where('os_p.object_type', '=', Program::class)
                    ->where('os_p.date_start', '<=', $today)
                    ->where('os_p.date_end', '>', $today);
            })
            ->addSelect(Db::raw('cds_study_organizations.*, max(os.service_id) as organization_order, max(cast(cast(os_p.id as bool) as int)) as program_order'))
            ->groupBy(['cds_study_organizations.id'])->orderBy('organization_order')->orderBy('program_order');


        $result = $result->with([ 'rates', 'views', 'programs.rates', 'programs.views'])->withProgramsSearch($result_category_ids, $parameters);
        $result = $result->paginate(15);
        $collection = $result->getCollection();
        $coll_top_one = $collection->where('organization_order', $services->get('top_one'))->shuffle();
        $coll_top_two = $collection->where('organization_order', $services->get('top_twothree'))->shuffle();
        $coll_money_programs = $collection->where('program_order', 1)->shuffle();
        $coll_free_programs = $collection->where('organization_order', null)->where('program_order', null)->shuffle();
        $coll_result_random = $coll_money_programs->merge($coll_free_programs);
        $result->setCollection($coll_result_random);
        $count_options = 0;
        if(!empty($parameters['program_params'])) $count_options += count($parameters['program_params']);
        if(!empty($parameters['org_params'])) $count_options += count($parameters['org_params']);

        if(!empty(Session::get('c', []))) $result->appends(['p' => get('p', []), 'c' => $parent_ids]);

        return [
            'coll_top_one' => $coll_top_one,
            'coll_top_two' => $coll_top_two,
            'organizations' => $result,
            'count_org'  => $count_org,
            'regions' => $regions,
            'count_options' => $count_options,
            'limits' => $limits,
            'categories' => $parent_ids
            ];
    }

    public function onRenderOrganizationDetail(){
        Session::pull('detail_org');
        Session::put('detail_org', [
            'program_params' => [],
            'max_price' => null,
            'min_price' => null,
            'max_term' => null,
            'min_term' => null

        ]);
        $organization_id = $this->property('organization_id');
        $limits = ProgramFormat::getLimits(['organization_id' => $organization_id, 'ids' => []]);
        if (!empty($limits)) $limits = $limits->toArray();
        $programs = Program::byOrganization($this->property('organization_id'))
            ->whereHas('program_formats', function($query){
                return $query->notFictive();
            })->notFictive();
        $regions = $this->onGetRegions($programs->count(DB::raw('DISTINCT sector_id')));
        $programs = $programs->with(['program_formats' => function($query){
            return $query->notFictive();
        }])->paginate(15);
        return [
            'programs' => $programs,
            'view_filter' => $this->property('view_filter'),
            'regions' => $regions,
            'organization_id' => $organization_id,
            'limits' => $limits
            ];

    }

    public function onPutOrganizationDetailPhrase(){
        Session::put('detail_org.phrases', post('phrases', ''));
        return $this->onFilterOrganizationDetail();
    }

    public function onPutOrganizationDetailPagination(){
        Session::put('detail_org.pagination', post('pagination', null));
        return $this->onFilterOrganizationDetail();
    }

    public function onPutOrganizationDetailSort(){
        $sort = Session::get('detail_org.sort', null);
        if(empty($sort)) Session::put('detail_org.sort', 'desc');
        else Session::put('detail_org.sort', null);
        return $this->onFilterOrganizationDetail();
    }

    public function onPutOrganizationDetailParameters(){
        Session::put('detail_org.program_params', array_keys(post('program_params', [])));
        Session::put('detail_org.max_price', post('max_price', null));
        Session::put('detail_org.min_price', post('min_price', null));
        Session::put('detail_org.max_term', post('max_term', null));
        Session::put('detail_org.min_term', post('min_term', null));
        return $this->onFilterOrganizationDetail();
    }

    public function onFilterOrganizationDetail(){
        $parameters = Session::get('detail_org');
        $paginate = !empty($parameters['pagination']) ? $parameters['pagination'] : 15;
        $sort = !empty($parameters['sort']) ? $parameters['sort'] : 'asc';
        $count_options = !empty($parameters['program_params']) ? count($parameters['program_params']) : 0;
        $organization_id = post('organization_id');
        $programs = Program::byOrganization($organization_id)
            ->notFictive()->whereHas('program_formats', function ($query) use ($parameters){
                return $query->notFictive()->filter($parameters);
            })
            ->when(!empty($parameters['program_params']), function($query) use ($parameters){
                return $query->parameters($parameters['program_params']);
            })->when(!empty($parameters['phrases']), function ($query) use ($parameters){
                return $query->searchByPhrase($parameters['phrases']);
            })->with(['program_formats' => function ($query) use ($parameters){
                return $query->filter($parameters);
            }])->orderBy('id', $sort);
        $regions = $this->onGetRegions($programs->count(DB::raw('DISTINCT sector_id')));
        $programs = $programs->paginate($paginate);
        return [
            "#programs_list_filter" => $this->renderPartial(
                '@organization_detail_result', ['programs' => $programs, 'organization_id' => $organization_id]),
            '#regions' => $regions,
            '#count_options' => "Фильтр ({$count_options} выбрано)",
            '#view_paginate' => "По {$paginate} штук"
                ];

    }


    protected function onGetRegions($count = 0){
        return $count . ' ' . Lang::choice('категория|категории|категорий', $count, [], 'ru');
    }

    protected function cityFilter(){
        return Organization::byCity();
    }

}
