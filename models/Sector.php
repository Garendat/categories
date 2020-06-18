<?php namespace Cds\Study\Models;

use Illuminate\Support\Facades\Lang;
use Model;
use October\Rain\Exception\ValidationException;
use Illuminate\Support\Facades\DB;
use System\Models\File;

/**
 * Model Отрасли
 */
class Sector extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SimpleTree;
    use \October\Rain\Database\Traits\Sortable;
    use \October\Rain\Database\Traits\Sluggable;

    /**
     * @var string The database table used by the model.
     */
    protected $table = 'cds_study_sectors';
    public $attributes = [
        'has_child' => 0
    ];
    protected $primaryKey = 'id';
    protected $slugs = ['slug' => 'name'];
    protected $hidden = ['parent_id', 'created_at', 'update_at'];
    protected $get_parent_programs = false;
    protected $isParent = false;
    public $isNotDelete = false;
    public $appends = ['link', 'search_link'];
    public $fillable = ['has_child', 'name', 'is_general', 'parent_id', 'sort_order', 'is_popular', 'seo_keywords',
        'seo_description', 'slug', 'lvl', 'org_count', 'program_count', 'image', 'seo_title', 'seo_text'];
    const PARENT_ID = 'parent_id';
    const SORT_ORDER = 'sort_order';

    public $jsonable = [
        'synonyms',
    ];

    public $rules = [
        'name' => 'required',
        'slug' => 'unique:cds_study_sectors,slug',
        'image' => 'nullable|max:2048|mimes:jpeg,bmp,png,jpg',
    ];

    public $customMessages = [
        'name.required' => 'Название обязательно',
        'name.unique' => 'Название должно быть уникальным',
        'slug.unique' => 'Значение slug должно быть уникальным',
        'image.max' => 'Логотип не должен превышать 2 мб',
        'image.mimes' => 'У логотипа могут быть расширения только jpeg bmp png jpg',
    ];

    public $belongsTo = [
    ];

    public $hasOne = [
        'counter' => [
            SectorCounter::class,
            'scope' => 'byCity'
        ],
    ];


    public $hasMany = [
        'programs' => [Program::class],
        'counters' => [
            SectorCounter::class
        ],
    ];
    public $attachOne = [
        'image' => [File::class],
        'general_image' => [File::class],
    ];

    // ============================= Before After ================================= //

    public function filterFields($fields, $context = null)
    {
        if(!empty($fields->{'parent'}->value)){
            $fields->{'is_general'}->disabled = true;
            $fields->{'is_general'}->value = false;
        }
        if(!$fields->{'is_general'}->value){
            $fields->{'general_image'}->disabled = true;
            $fields->{'general_image'}->value = false;
        }
    }

    public function beforeSave()
    {
        if(empty($this->lvl))
            $this->lvl = !empty($this->parent) ? $this->parent->lvl + 1: 1;
        elseif($this->attributes['parent_id'] !== $this->original['parent_id'])
        {
            $this->lvl = !empty($this->parent) ? $this->parent->lvl + 1: 1;
            $difference = $this->lvl - $this->original['lvl'];
            if($difference)
            {
                $this->getAllChildren()->each(function($model) use($difference){
                    $model->increment('lvl', $difference);
                });
            }
        }
    }

    public function getOrgCountViewAttribute(){
        $count = !empty($this->counter->org_count) ? $this->counter->org_count : 0;
        $str = Lang::choice('организация|организации|организаций', $count, [], 'ru');
        return "{$count} {$str}";
    }
    public function getOrgCountAttribute(){
        $count = !empty($this->counter->org_count) ? $this->counter->org_count : 0;
        return $count;
    }

    public function getProgramCountViewAttribute(){
        $count = !empty($this->counter->program_count) ? $this->counter->program_count : 0;
        $str = Lang::choice('программа|программы|программ', $count, [], 'ru');
        return "{$count} {$str}";
    }

    public function getProgramCountAttribute(){
        $count = !empty($this->counter->program_count) ? $this->counter->program_count : 0;
        return $count;
    }

    public function getOrgCountAllAttribute(){
        $count = !empty($this->counters) ? $this->counters->sum('org_count') : 0;
        return $count;
    }

    public function getProgramCountAllAttribute(){
        $count = !empty($this->counters) ? $this->counters->sum('program_count') : 0;
        return $count;
    }

    public function insertToInlineTree($parent_id = 0){
        DB::table('cds_study_inline_tree')->insert(['sector_id' => $parent_id, 'child' => $this->id]);
    }

    public function deleteRootInTheInlineTree($category_id = null){
        DB::table('cds_study_inline_tree')->whereIn('sector_id', $category_id)->delete();
    }

    public function deleteChildrenInTheInlineTree($categories_id = []){
        if(!empty($categories_id))
            DB::table('cds_study_inline_tree')->whereIn('child', $categories_id)->
                whereNotIn('sector_id', $categories_id)->delete();
    }

    public function deleteCategoriesInTheInlineTree($category_ids = []){
        DB::table('cds_study_inline_tree')->whereIn('sector_id', $category_ids)->delete();
    }

    public function insertToManyParentsToInlineTree($parents_id = []){
        $arr_result = [];
        foreach($parents_id as $parent_id){
            $arr_result[] = ['sector_id' => $parent_id, 'child' => $this->id];
        }
        if(!empty($arr_result)) DB::table('cds_study_inline_tree')->insert($arr_result);
    }

    public function deleteAllInTheInlineTree($categories = []){
        if(!empty($categories)){
            DB::table('cds_study_inline_tree')->whereIn('sector_id', $categories)
                ->orWhereIn('child', $categories)->delete();
        }
    }

    public function insertChildrenAndParentsToInlineTree($parents_id = [], $children_id = []){
        if(!empty($parents_id) && !empty($children_id)){
            $arr_to_insert = [];
            foreach($parents_id as $parent_id){
                $arr = ['sector_id' => $parent_id];
                foreach ($children_id as $child){
                    $arr_to_insert[] = $arr + ['child' => $child];
                }
            }
            DB::table('cds_study_inline_tree')->insert($arr_to_insert);
        }
    }

    public function getChildrenInlineTree(){
        return DB::table('cds_study_inline_tree')->where('sector_id', $this->id)->get();
    }

    public static function getChildrenInlineTreeStatic($sector_id = null){
        return DB::table('cds_study_inline_tree')->where('sector_id', $sector_id)->lists('child');
    }

    public static function getChildrenToManyParentsInlineTree($parents_ids = []){
        return DB::table('cds_study_inline_tree')->whereIn('sector_id', $parents_ids)->get();
    }

    public function getParentsInlineTree(){
        return DB::table('cds_study_inline_tree')->where('child', $this->id)->get();
    }

    public function getParentsInlineTreeIds(){
        return DB::table('cds_study_inline_tree')->where('child', $this->id)->lists('sector_id');
    }


    // метод для тех кто добавляет не через админку категории
//    public function createCetegoryE(){
//        $categories = Sector::get();
//        foreach ($categories as $category){
//            $c = $category->getAllChildren()->pluck('id');
//            $c[] = $category->id;
//            $this->insertChildrenAndParentsToInlineTree([$category->id], $c);
//        }
//        self::updateCounters();
//    }



    public function afterSave()
    {
        // если категорию только создали
        if(empty($this->original['id']))
        {
            // если родитель не пустой то обновляем ему поля числа детей
            if(!empty($this->attributes['parent_id'])) $this->parent->updateChildren();

            // при создании категории s добавляем себя всем родителям и себя в том числе (НОВОЕ ЛИНЕЙНОЕ ДЕРЕВО)
            $parents = $this->getAllRoot();
            $this->insertToManyParentsToInlineTree($parents);
        }
        // Тут реализована логика перемещения категорий как в рамках одного уровня так и между уровнями
        elseif($this->original['parent_id'] !== $this->attributes['parent_id'])
        {
            // Вызываем  прошлого родителя чтобы он поменял у себя кол-во ближайших потомков
            if(!empty($this->original['parent_id'])) {
                $old_parent = self::find($this->original['parent_id']);
                $old_parent->updateChildren();
            }
            // у нового родителя также меняем кол-во ближайших потомков
            if(!empty($this->attributes['parent_id'])) $this->parent->updateChildren();

            // просчет линейного дерева для 2 уровня в отдельную таблицу (переделываем под 1 уровень в том числе). НУ ЧЕ НАРОД, ПОГНАЛИ????
            // Я ПРИДУМАЛ РЕАЛИЗАЦИЮ НОВОГО АЛГОРИТМА ЛИНЕЙНОГО ДЕРЕВА для ВСЕХ УРОВНЕЙ
            $this->movingCategory();
        }

    }

    public function afterDelete(){
        if(!empty($this->parent)) $this->parent->updateChildren();
        //УДАЛЕНИЕ ИЗ ЛИНЕЙНОЙ ТАБЛИЦЫ
        $categories = $this->getAllChildren()->pluck('id');
        $categories[] = $this->id;
        $this->deleteAllInTheInlineTree($categories);
        // Также удаляем все дерево из основной таблицы
        $this->whereIn('id', $categories)->delete();

    }

    public function updateChildren(){
        $this->update(['has_child' => $this->children()->count()]);
    }

    public function beforeValidate(){
        if(!empty($this->attributes['parent_id'])){
            if(!$this->get_parent_programs && self::find($this->attributes['parent_id'])->programs->count() > 0)
                throw new ValidationException(['get_parent_programs' => 'Для того чтобы создать категорию вам нужно
                                                переместить программы, нажмите соответствующую кнопку!']);


        }
    }

    public function beforeCreate()
    {

    }

    public function afterCreate(){
        $this->moveProgramsToLeafSector();
    }

    // ============================= Getters Setters ================================= //
    protected function moveProgramsToLeafSector() {
        if($this->get_parent_programs && !empty($this->parent)){
            $this->parent->programs()->update(['sector_id' => $this->id]);
//            self::updateCounters();
        }
    }

    public function getSubscribersAttribute(Array $contactInfo){
        //TODO: коллекция кураторов организаций с действующей тарифной опцией
    }

    protected function setGetParentProgramsAttribute($value){
        $this->get_parent_programs = $value;
    }

    protected function getLinkAttribute(){
        if($this->attributes['slug']){
            $slug = $this->attributes['slug'];
            return "/sectors/{$slug}";
        }
        return null;
    }

    public function getSearchLinkAttribute(){
        if($this->attributes['slug']){
            return "/education/category/{$this->slug}-{$this->id}";
        }
        return null;
    }


    // ============================= Scopes filter ================================= //

    public function scopeNotChild($query){
        return $query->doesntHave('children')->withOut('children');
    }

    public function scopeGetRootCategories($query){
        return $query->whereNull('parent_id');
    }

    public function scopeGetCategoriesByLevel($query, $level = null){
        return $query->whereLvl($level);
    }

    public function scopeLastLevel($query) {
        return $query->where('has_child', 0);
    }

    public function scopeByPhrase($query, $phrase = ''){

        if(!empty($phrase)){
            $order = $phrase . '%';
            $phrase = "%{$phrase}%";
        }
        $result = $query->where('name', 'ILIKE', $phrase)->orWhereRaw("synonyms::text ILIKE '{$phrase}'");
        if(!empty($order)) $result->orderByRaw("name ILIKE '{$order}' desc")->orderBy('name');
        return $result;
    }

    // накладывает условие поиска по любой фразе из списка
    public function scopeByPhrases($query, $phrases = '') {
        if (is_string($phrases)) {
            $phraseList = preg_split( "/ [,;\.]+ /", $phrases, null, PREG_SPLIT_NO_EMPTY);
        } elseif (is_array($phrases)) {
            $phraseList = $phrases;
        } else {
            $phraseList = array_wrap($phrases);
        }
        $query = $query->where(function ($q) use ($phraseList) {
            foreach ($phraseList as $phrase) {
                if (empty($phrase))
                    continue;
                $q->orWhere('name', 'ILIKE', "%{$phrase}%");
                $q->orWhereRaw("synonyms::text ILIKE '%{$phrase}%'");
            }
            return $q;
        });

        return $query;
    }

    public function scopeIsPopular($query){
        return $query->where('is_popular', true);
    }

    public function scopeIsGeneral($query){
        return $query->where('is_general', true);
    }

    // ============================= Make Scopes ================================= //

    // ============================= Public Methods ================================= //

    public static function getCategoryBySlug($slug = ''){
        $category = self::where('slug', $slug)->get();
        if(!empty($category)) $category = $category->first();
        return $category;
    }

    public static function getCategoryBySlugAndId($str = ''){
        $category = null;
        if(!empty($str)){
            $res = explode('-', $str);
            $id = array_pop($res);
            $slug = implode('-', $res);
            $category = self::where('slug', $slug)->where('id', $id)->get();
            if(!empty($category)) $category = $category->first();
        }
        return $category;
    }

    public function getRootWithChildren(){
        return $this->parent()->with('getRootWithChildren')->with('children');
    }

    public function getRoot(){
        return $this->parent()->with('getRoot');
    }

    public function getAllParentsWithChildren(){
        $parent_objects = [];
        $ids = [$this->id];
        $parents = $this->getRootWithChildren()->first();
        $parents = !empty($parents) ? $parents->toArray(): $parents;
        while(true)
        {
            if(empty($parents)) break;
                $ids[] = $parents['id'];
                $parent_objects[$parents['id']] = $parents['children'];
            $parents = $parents['get_root_with_children'];
        }
        if($this->getChildCount())
            $parent_objects[$this->id] = $this->getChildren()->toArray();

        ksort($parent_objects);
        return [$ids, $parent_objects];
    }

    public function getFindTreeToLvl($lvl = null){
        $result = [];

        if($difference = $lvl - $this->lvl){
            if($difference > 0){
                $children = $this->getAllChildren()->where('lvl', $lvl);
                $result = !empty($children) ? $children->pluck('id')->toArray(): [];
            }
            else{
                $parents = $this->getRoot()->first();
                $parents = !empty($parents) ? $parents->toArray(): $parents;
                while(!empty($parents)){
                    if($parents['lvl'] == $lvl)   $result[] = $parents['id'];
                    $parents = $parents['get_root'];
                }
            }

        }
        else {   $result[] = $this->id;   };

        return $result;
    }

    public function getFindParentToLvl($lvl = 1, $object_id = null){
        $result = null;
        if ($object_id)
        {
            $parents = self::find($object_id);
            if($parents->lvl == $lvl) return $parents->id;
            $parents = $this->getRoot()->first();
        }
        else
            $parents = $this->getRoot()->first();
        $parents = !empty($parents) ? $parents->toArray(): $parents;
        while(!empty($parents))
        {
            if($parents['lvl'] == $lvl)
            {
                $result = $parents['id'];
                break;
            }
            $parents = $parents['get_root'];
        }
        return $result;
    }

    public function getFindManyParentsToLvl($lvls = [], $object_id = null){
        $result = [];
        if ($object_id) {
            $parents = self::find($object_id);
            if(in_array($parents->lvl, $lvls) && count($lvls) == 1) return [$parents->id];
            elseif(in_array($parents->lvl, $lvls)) $result[] = $parents->id;
        }
        $parents = $this->getRoot()->first();
        $parents = !empty($parents) ? $parents->toArray(): $parents;
        while(!empty($parents)) {
            if(in_array($parents['lvl'], $lvls))  $result[] = $parents['id'];
            $parents = $parents['get_root'];
        }
        return $result;
    }

    public static function getCategoriesHasOrNoneChild($phrases = '', $arr = []){
        $categories = self::whereIn('id', $arr)->when(!empty($phrases),
            function($q) use($phrases){
            $q->orWhereRaw($phrases);
        })->get();

        $result_category_ids = [];
        $parent_ids = [];
        foreach($categories as $category) {
            if(!empty($category->has_child)) $parent_ids[] = $category->id;
            else $result_category_ids[] = $category->id;
        }

        return ['none_child' => $result_category_ids , 'has_child' => $parent_ids];
    }

    public static function getCategoriesByIdAndPhrases($phrases = '', $parent_ids = []){
        $filter_categories = self::getCategoriesHasOrNoneChild($phrases, $parent_ids);
        $result_category_ids = $filter_categories['none_child'];
        $parent_ids = $filter_categories['has_child'];
        $lvl = 1;

        while(!empty($parent_ids))
        {
            $categories = self::select('id', 'has_child')->whereLvl($lvl)->where(function($query) use($phrases, $parent_ids){
                $query->whereIn('parent_id', $parent_ids);
                if(!empty($phrases)) $query->orWhereRaw($phrases);
            })->get();
            if($lvl > 2) $parent_ids = [];
            foreach($categories as $category){
                if(!empty($category->has_child)) $parent_ids[] = $category->id;
                else $result_category_ids[] = $category->id;
            }
            $parent_ids = array_unique($parent_ids);
            $lvl++;
        }
        return $result_category_ids;
    }

    public function getSeoArray(){
        return !empty($this->seo_description) ? [
            'text' => $this->seo_text,
            'title' => $this->seo_title,
            'description' => $this->seo_description,
            'keywords' => $this->seo_keywords, 'image' => $this->getImagePath()] : [];

    }

    public function getImagePath(){
        return !empty($this->image) ? $this->image->getPath() : null;
    }

    public function getGeneralImage(){
        return $this->general_image->getThumb(560, 585, ['mode' => 'crop']);
    }


    public function getAllRoot()
    {
        $parent_ids = [$this->id];
        $parents = $this->getRoot()->first();
        $parents = !empty($parents) ? $parents->toArray(): $parents;
        while(!empty($parents))
        {
            $parent_ids[] = $parents['id'];
            $parents = $parents['get_root'];
        }
        return $parent_ids;
    }

    protected function movingCategory(){
        // РЕАЛИЗАЦИЯ ПЕРЕМЕЩЕНИЯ КАТЕГОРИЙ
        $children = $this->getAllChildren()->pluck('id');
        $children[] = $this->id;
        $this->deleteChildrenInTheInlineTree($children);
        $parents = $this->getAllRoot();
        $this->insertChildrenAndParentsToInlineTree($parents, $children);
        // И наконец пересчитаем все программы и все организации (уникальные значения) для старых и новых родителей
        self::updateCounters();
    }

    static function updateCounters($ids = [], $level = null) {
        \DB::update('
            UPDATE cds_study_sector_counters set org_count = 0, program_count = 0;
        ');
        \DB::delete('
            DELETE FROM cds_study_sector_counters WHERE realm_id IS NULL;
        ');

        \DB::insert('
            INSERT INTO "cds_study_sector_counters" (sector_id, realm_id, org_count, program_count)
            SELECT * FROM (
                SELECT
                    cds_study_sectors.id AS sector_id,
                    COALESCE(cds_study_organizations.realm_id, cds_study_realms.parent_id) AS realm_id,
                    COUNT( distinct cds_study_organizations.id) as org_count,
                    COUNT( distinct CASE WHEN cds_study_programs.fictive THEN NULL ELSE cds_study_programs.id END) as program_count
                FROM
                    cds_study_sectors
                    JOIN cds_study_inline_tree
                        ON cds_study_sectors.id = cds_study_inline_tree.sector_id
                    JOIN cds_study_programs
                        ON cds_study_inline_tree.child = cds_study_programs.sector_id
                        AND cds_study_programs.status = 2 AND cds_study_programs.original_id IS NULL
                        AND cds_study_programs.deleted_at IS NULL
                    JOIN cds_study_organizations
                        ON cds_study_organizations.id = cds_study_programs.organization_id
                        AND cds_study_organizations.status = 2 AND cds_study_organizations.original_id IS NULL
                        AND cds_study_organizations.deleted_at IS NULL
                    JOIN cds_study_realms
                        ON cds_study_realms.id = cds_study_organizations.realm_id
                    JOIN cds_study_program_formats
                        ON cds_study_program_formats.program_id = cds_study_programs.id
                        AND cds_study_program_formats.original_id IS NULL
                        AND cds_study_program_formats.deleted_at IS NULL
                WHERE NOT cds_study_organizations.realm_id IS NULL
                GROUP BY GROUPING SETS ( (cds_study_sectors.id, cds_study_organizations.realm_id), (cds_study_sectors.id, cds_study_realms.parent_id) )
            ) cnt
            ON CONFLICT (sector_id, realm_id)
                DO UPDATE SET
                    org_count = EXCLUDED.org_count,
                    program_count = EXCLUDED.program_count
            '
        );
    }

    public function getSeoImageAttribute() {
        if(!empty($this->image)) $this->image['path'];
        return null;
    }

    public function scopeWithCounter($query){
        return $query->with(['counters' => function($query){
            return $query->byCity();
        }]);
    }

    // ============================= Protected Methods ================================= //

}
