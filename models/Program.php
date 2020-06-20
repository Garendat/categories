<?php namespace Cds\Study\Models;

use Carbon\Carbon;
use Cds\Study\Models\ProgramFormat;
use Cds\Study\Models\Promocode;
use Illuminate\Support\Facades\DB;
use Model;
use October\Rain\Exception\ValidationException;
use RainLab\User\Facades\Auth;
use Session;
use Cds\Study\Models\UserAction;
use Cds\Study\Models\ObjectValue;
use System\Models\File;

/**
 * Model
 */
class Program extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'cds_study_programs';
    public $current_format = null;


    public $attributes = [
        'status' => 1,
        'using_promocode' => false,
        'fictive' => false
    ];
    /**
     * @var array Validation rules
     */
    public $rules = [
        'name' => 'required',
        'sector_id' => 'required|integer',
        'organization_id' => 'required|integer|exists:cds_study_organizations,id',
        'status' => 'nullable',
        'description' => 'nullable',
        'using_promocode' => 'nullable',
    ];

    public $customMessages = [
        'name.required' => 'Имя обязательно',
        'sector_id.required' =>'Категория обязательна',
        'sector_id.integer' =>'Категория должна быть числом',
        'sector_id.exists' =>'Категории не существует',
        'organization_id.required' => 'Организация обязательна',
        'organization_id.integer' => 'Индентификатор организации должен быть числом',
        'organization_id.exists' => 'Организации не существует',
    ];

    public $fillable = ['name', 'sector_id', 'organization_id', 'status', 'contact_person_id',
        'description', 'annotation', 'using_promocode', 'seo_title', 'seo_description', 'seo_keywords', 'fictive', 'original_id'];

    public $appends = ['link_edit'];

    public $dates = ['deleted_at'];

    public $belongsTo = [
        'organization' => [Organization::class],
        'sector' => [Sector::class],
        'contact_person' => [ContactPerson::class],
        'original' => [
            self::class,
            'otherKey' => 'id',
            'key' => 'original_id'
        ]
    ];

    public $belongsToMany = [];


    public $hasMany = [
        'program_formats' => [
            ProgramFormat::class,
            'delete' => true],
        'parameters' => [
            ObjectValue::class,
            'key' => 'object_id',
            'otherKey' => 'id',
            'scope' => 'byPrograms',
            'delete' => true
        ],
        'parameters_search' => [
            Parameter::class,
            'table' => 'cds_study_object_values',
            'key' => 'object_id',
            'otherKey' => 'id',
            'scope' => 'byPrograms'
        ],
    ];

    public $hasOne = [
        'duplicate' => [
            self::class,
            'key' => 'original_id',
            'otherKey' => 'id',
            'scope' => 'withOutModerate'
        ]
    ];


    public $morphOne = [
        'notification' => [ Notification::class,
            'name' => 'object',
        ],
        'my_rate' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'rate'",
            'scope' => 'my'
        ],
        'my_favorite' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'favorite'",
            'scope' => 'my'
        ],
        'priority' => [
            ObjectService::class,
            'name' => 'object',
            'scope' => 'active'
        ],
    ];

    public $morphMany = [
        'bills' => [
            Bill::class,
            'name' => 'object',
        ],

        'services' => [
            ObjectService::class,
            'name' => 'object',
        ],

        'rates' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'rate'"
        ],

        'views' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'view'"
        ],
        'requests' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'request'"
        ],
        'promocode_clicks' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'promocode'"
        ],
        'phone_clicks' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'phone'"
        ],
    ];


    // ============================= Duplicate ================================= //
    //==========================================================================================//

    public $statuses = [
        0 => '<не задан>',
        1 => 'Ожидает подтверждения',
        2 => 'Активна',
        3 => 'Отклонено'
    ];

    const UPDATE_STATUSES = [1, 3];

    const MODERATE_STATUS = 1;

    public $dirtyFields = ['name', 'description', 'annotation', 'sector_id'];

    protected $dirtyRelationsAttachOne = [];
    protected $dirtyRelationsHasMany = ['program_formats'];
    protected $dirtyRelationsHasOne = [];
    protected $dirtyRelationsAttachMany = [];
    public $sessionKey = null;

    public function scopeWithOrganizationModerate($query){
        return $query->with(['organization' => function($query){
            return $query->withOutModerate();
        }]);
    }

    public function scopeWithFormatsModerate($query){
        return $query->with(['program_formats' => function($query){
            return $query->withOutModerate();
        }]);
    }


    public function isUpdateStatus(){
        return in_array($this->status, self::UPDATE_STATUSES);
    }

    public function getDuplicateOrIsUpdate(){
        if($this->isUpdateStatus() || $this->isDuplicate())      return $this;
        elseif($this->hasDuplicate()) return $this->duplicate;
        else                          return $this->createDuplicate();
    }

    protected function setSession(){
        $this->sessionKey = uniqid('session_key', true);
        if(empty($this->sessionKey)) $this->sessionKey = 'session_key' . str_random(15);
    }

    public function duplicateValidateFields($duplicate, $attrs){
        foreach($this->dirtyFields as $field){
            if(!empty($attrs[$field])) $duplicate->{$field} = $attrs[$field];
        }
        return $duplicate->validate();
    }


    public function createDuplicate($attrs = []){
        $this->setSession();
        $duplicate = $this->replicate();
        $duplicate->original_id = $this->id;
        $duplicate->status = 1;
        $this->duplicateValidateFields($duplicate, $attrs);

        foreach($this->dirtyRelationsAttachOne as $relation){
            $this->duplicateAttachOne($duplicate, $relation, $this->{$relation});
        }

        foreach($this->dirtyRelationsHasMany as $relation){
            $entries = $this->{$relation}()->getWithOutOriginal()->get();
            $this->duplicateHasMany($duplicate, $relation, $entries);
        }

        foreach($this->dirtyRelationsAttachMany as $relation){
            $entries = $this->{$relation}()->get();
            $this->duplicateAttachMany($duplicate, $relation, $entries);
        }

        $duplicate->save(null, $this->sessionKey);
        return $duplicate;
    }

    public function duplicateAttachOne($duplicate, $relation, $entry){
        if(empty($entry)) return;
        $file = (new CdsFile())->fromURL($entry->getPath());
        $file->is_public = true;
        $file->save(null, $this->sessionKey);
        $duplicate->{$relation}()->add($file, $this->sessionKey);
    }

    public function duplicateHasMany($duplicate, $relation, $entries){
        foreach($entries as $entry) {
            $e = $entry->getDuplicateOrIsUpdate();
            $duplicate->{$relation}()->add($e, $this->sessionKey);
        }
    }

    public function duplicateAttachMany($duplicate, $relation, $entries){
        foreach($entries as $entry) {
            if(empty($entry)) continue;
            $file = (new CdsFile())->fromURL($entry->getPath());
            $file->is_public = true;
            $file->save(null, $this->sessionKey);
            $duplicate->{$relation}()->add($file, $this->sessionKey);
        }
    }

    public function scopeGetDirty($query){
        return $query->withOutModerate()->where(function($query){
            $query->whereNotNull('original_id')->orWhereIn('status', self::UPDATE_STATUSES);
        });

    }

    public function scopeGetDirtyForController($query){
        return $query->withOutModerate()->where(function($query){
            $query->whereNotNull('original_id')->orWhere('status', self::MODERATE_STATUS);
        });

    }

    public function scopeGetWithOutOriginal($query){
        return $query->withOutModerate()->doesntHave('duplicate');
    }

    public function scopeWithOutModerate($query){
        $query->withOutGlobalScope('notDuplicate')->withOutGlobalScope('isActiveStatus');
    }


    public function isDuplicate(){
        return !empty($this->original_id);
    }

    public function hasDuplicate(){
        return !empty($this->duplicate()->count());
    }

    public function isDirtyAttributes($attributes = []){
        foreach($this->dirtyFields as $field){
            if(!empty($attributes[$field]) && ($this->{$field} != $attributes[$field]))
                return true;
        }

        foreach($this->dirtyRelationsAttachOne as $relation){
            if(!empty($attributes[$relation])){
//                $exist_relations = $this->{$relation}()->lists('id');
                return true;
            }
        }

        foreach($this->dirtyRelationsHasMany as $relation){
            $exist_relations = $this->{$relation}()->getDirty()->count();
            if($exist_relations) return true;
        }

        foreach($this->dirtyRelationsAttachMany as $relation){
            if(!empty($attributes[$relation])){
                return true;
//                $exist_relations = $this->{$relation}()->lists('id');
//                sort($exist_relations);
//                $new_relations = [];
//
//                foreach($attributes[$relation] as $object){
//                    if(!empty($object) && is_object($object))
//                        $new_relations[] = $object->id;
//                }
//                sort($new_relations);
//                if($exist_relations !== $new_relations) return true;
            }
        }
        return false;
    }

    public function updateModelOrCreateDuplicate($attributes = []){

        if ( $this->isOriginal() && !$this->isDirtyAttributes($attributes)) {
            $this->fill($attributes)->save();
            return $this;
        } elseif($this->isUpdateStatus() && $this->isOriginal()){
            $this->fill(['status' => 1] + $attributes)->save();
            return $this;
        } elseif($this->isDuplicate() && !$this->isDirtyAttributes($attributes)){
            $model = $this->getOriginalModel();
            $model->update($attributes);
            return $this;
        } else {
            $attrs      = $this->getOriginalAndDirtyAttributes($attributes);
            $original   = $this->getOriginalModel();
            $original->fill($attrs['original'])->save();
            $old_duplicate = true;
            if($this->isDuplicate())       $duplicate = $this;
            elseif($this->hasDuplicate())  $duplicate = $this->duplicate;
            else {
                $old_duplicate = false;
                $duplicate = $this->createDuplicate($attrs['duplicate']);
                foreach($attrs['duplicate'] as $field => $attr){
                    if(in_array($field, $this->dirtyRelationsAttachOne) ||
                        in_array($field, $this->dirtyRelationsAttachMany))
                        $duplicate->{$field} = $attr;
                }
                $duplicate->save();
            }
            if($old_duplicate){
                $duplicate->fill(['status' => 1] + $attrs['duplicate'])->save();
            }

            $original   = $this->getOriginalModel();
            $original->fill($attrs['original'])->save();
            return $duplicate;
        }

    }

    public function getOriginalAndDirtyAttributes($attributes = []){
        $duplicate_attrs = [];
        $original_attrs = [];
        foreach($attributes as $key => $value){
            if(in_array($key, $this->dirtyFields)) $duplicate_attrs[$key] = $value;
            elseif(in_array($key, $this->dirtyRelationsAttachOne)) $duplicate_attrs[$key] = $value;
            elseif(in_array($key, $this->dirtyRelationsAttachMany)) $duplicate_attrs[$key] = $value;
            elseif(in_array($key, $this->dirtyRelationsHasMany)) $duplicate_attrs[$key] = $value;
            else $original_attrs[$key] = $value;
        }
        return ['original' => $original_attrs, 'duplicate' => $duplicate_attrs];
    }

    public function deleteWithOriginalOrDuplicate(){
        if($this->hasDuplicate()){
            $duplicate = $this->duplicate;
            $duplicate->forceDelete();
            $this->delete();
        }
        elseif($this->isDuplicate()){
            $original = $this->getOriginalModel();
            $original->delete();
            $this->forceDelete();
        }
        else {
            if(in_array($this->status, self::UPDATE_STATUSES))
                $this->forceDelete();
            else
                $this->delete();
        }
    }

    public function ResetDuplicate(){
        if($this->hasDuplicate()){
            $duplicate = $this->duplicate;
            $duplicate->deleteDuplicate();
            return $this;
        }
        elseif($this->isDuplicate()){
            $original = $this->getOriginalModel();
            $this->deleteDuplicate();
            return $original;
        }
        return false;
    }

    public function deleteDuplicate(){
        foreach($this->dirtyRelationsHasMany as $relation){
            $collection = $this->{$relation}()->withOutModerate()->get();
            $collection->each(function($model){
                $model->deleteDuplicate();
            });
        }
        $this->forceDelete();
    }


    public function getOriginalOrDuplicate(){
        if($this->hasDuplicate()) return $this->duplicate;
        else return $this;
    }

    public function getOriginalModel(){
        return !empty($this->original_id) ? $this->original()->first() : $this;
    }

    public function getOriginalId(){
        return !empty($this->original_id) ? $this->original_id : $this->id;
    }

    public function isOriginal(){
        return empty($this->original_id);
    }

    public static function boot(){
        parent::boot();

        self::addGlobalScope('notDuplicate', function($query){
            return $query->whereNull('cds_study_programs.original_id');
        });

        self::addGlobalScope('isActiveStatus', function($query){
            return $query->whereNotIn('cds_study_programs.status', self::UPDATE_STATUSES);
        });
    }

    // ============================= Duplicate ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


    // ============================= Move Duplicate ================================= //
    //==========================================================================================//

    protected $key = 'program_id';
    public $movingDuplicate = false;

    public function saveDuplicate($status = 2, $parent_key ='', $parent_value = null){
        if(!empty($this->original_id) && $status == 2){
            $original = $this->getOriginalModel();
            $this->saveDuplicateFields($original);
            $this->saveDuplicateHasMany($status, $original);
            $this->saveDuplicateAttachOne($original);
            $this->saveDuplicateAttachMany($original);
            $original->movingDuplicate = true;
            $original->save();
            $this->forceDelete();
        } else {
            $this->status = $status;
            if(!empty($parent_key) && !empty($parent_value)) $this->{$parent_key} = $parent_value;
            $this->save();
            $this->saveDuplicateHasMany($status);
        }
    }

    public function saveDuplicateFields($original = null){
        foreach($this->dirtyFields as $field){
            $original->{$field} = $this->{$field};
        }
    }


    public function saveDuplicateAttachOne($original = null){
        $this->load($this->dirtyRelationsAttachOne);
        $original->load($this->dirtyRelationsAttachOne);
        foreach($this->dirtyRelationsAttachOne as $relation){
            if(!empty($original->{$relation})){
                $original->{$relation}->forceDelete();
            }
            if(!empty($this->{$relation})){
                $original->{$relation}()->add($this->{$relation});
                $this->reloadRelations($relation);
            }
        }
    }

    public function saveDuplicateHasMany($status = 2, $original = null){
        foreach($this->dirtyRelationsHasMany as $relation){
            $entries = $this->{$relation}()->withOutModerate()->get();
            foreach($entries as $entry){
                if($status == 2 && !empty($original)) $entry->saveDuplicate($status, $this->key, $original->id);
                else $entry->saveDuplicate($status);
            }
            $this->reloadRelations($relation);
        }
    }

    public function saveDuplicateAttachMany($original = null){
        $this->load($this->dirtyRelationsAttachMany);
        $original->load($this->dirtyRelationsAttachMany);
        foreach($this->dirtyRelationsAttachMany as $relation){
            if($original->{$relation}->count()){
                $original->{$relation}->each(function($model){
                    $model->forceDelete();
                });
            }
            if($this->{$relation}->count()){
                $original->{$relation}()->addMany($this->{$relation});
                $this->reloadRelations($relation);
            }
        }
    }

    // ============================= Move Duplicate ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//





// ================================= Events This Model ==================================== //
//==========================================================================================//


    public function beforeSave()
    {
        if(!empty($this->sector))
        {
            if(!empty($this->sector->has_child) && $this->status == 2)
                throw new ValidationException(['sector' => 'Выбрана категория не последнего уровня!']);
        }

    }

    public function afterSave(){
        if(empty($this->fictive)){

            self::where('organization_id', $this->organization_id)->where('fictive', true)->with('program_formats')->get()->each(function($model){
                $model->forceDelete();
            });
        }
        $this->createNotify();
    }

    public function afterDelete(){
        Sector::updateCounters();
    }

// ================================= Events This Model ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



// ============================= Getters Setters This models ================================= //
//==========================================================================================//

    public function setOrganizationIdAttribute($value) {
        $this->attributes['organization_id'] = (empty($value) ? null : $value);
    }

    public function getStatusCodeAttribute(){
        return $this->statuses[$this->attributes['status']];
    }

    public function getStatusOptions($keyValue = null)
    {
        return $this->statuses;
    }

//    public function getSectorOptions($keyValue = null)
//    {
//        return Sector::select('id', 'name')->lists('name', 'id');
//    }

    public function getLinkEditAttribute()
    {
        if(!empty($this->original_id)) $id = $this->original_id;
        else $id = !empty($this->attributes['id']) ? $this->attributes['id'] : $this->id;
        return "/lk/programs/edit/{$id}";
    }

    public function getLinkAttribute()
    {
        return "/education/programs/{$this->id}";
    }

    public function getLinkTestAttribute(){
        return "/programs/moderate/{$this->id}";
    }

    //    protected function getStatusAttribute()
//    {
//        return $this->statuses[$this->attributes['status']];
//    }

    public function getContactPersonIdOptions(){
        $org = $this->organization()->withOutModerate()->first();
        return !empty($org) ? $org->contact_people()->lists('name', 'id') : [];
    }

// ============================= Getters Setters This models ================================= //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




// ================================= Formats ==================================== //
//==========================================================================================//

    public function getMinPriceAttribute()
    {
        return $this->program_formats->min('current_price');
    }

    public function getMaxPriceAttribute()
    {
        return $this->program_formats->max('current_price');
    }

    public function getMinPriceViewAttribute(){
        $price = $this->min_price;
        $result = !empty($price) ? "{$price}  ₽" : 'Не указано';
        return  $result;
    }

    public function getFormatWithMinPrice()
    {
        return $this->program_formats->sortBy('current_price')->first();
    }

    public function getMinTermViewAttribute()
    {
        $min_obj = $this->program_formats->sortBy('term_to_sec')->first();
        return $min_obj ? $min_obj->term_view : 'Не указано';
    }
    public function getMinTermToSecAttribute()
    {
        return $this->program_formats->min('term_to_sec');
    }

    public function getMaxTermToSecAttribute()
    {
        return $this->program_formats->max('term_to_sec');
    }

    public function scopeHasFormats($query){
        return $query->whereHas('program_formats');
    }


// ================================= Formats ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



// ================================= Actions ==================================== //
//==========================================================================================//

    // получение среднего значения оценок
    public function getAllRatesAttribute(){
        $sum = $this->rates->sum('value');
        $count = $this->rates->count();
        return $count ? $sum / $count : $count;
    }
    //подсчет просмотров
    public function getAllViewsAttribute(){
        return $this->views->count();
    }

    public function createActionWithOrganization($attributes = []){
        if (Auth::check()) $attributes['user_id'] = Auth::id();
        else   $attributes['session_token'] = Session::get('_token');
        $attributes['action'] = 'request';
        $this->requests()->create($attributes);
        $this->organization->requests()->create($attributes);
    }

    public function createRequest(){
        $this->createActionWithOrganization(['action' => 'request']);
    }

    public function createPhoneClick(){
        $this->createActionWithOrganization(['action' => 'phone']);
    }

    public function createPromocodeClick(){
        $this->createActionWithOrganization(['action' => 'promocode']);
    }

    public function scopeByOrganization($query, $id = null){
        return $query->where('organization_id', $id);
    }

    // создаем просмотр себе и чтобы лишний раз в базу не бегать создаем просмотр организации
    public function createView($attributes = [])
    {
        UserAction::createViews(['object_type' => self::class, 'object_id' => $this->id] + $attributes);
        UserAction::createViews(['object_type' => Organization::class, 'object_id' => $this->organization_id] + $attributes);
    }

    public function scopeHasActions($query, $actions = null, $onlyMy = true) {
        return $query->whereHas('actions', function ($actionsQ) use ($actions, $onlyMy) {
            $actionsQ = $actionsQ->action($actions);
            if ($onlyMy)
                $actionsQ = $actionsQ->my();
        });
    }

    public function scopeHasMyFavorite($query) {
        return $query->whereHas('my_favorite');
    }

// ================================= Actions ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



// ================================= Fictive ==================================== //
//==========================================================================================//

    public static function setFictivePrograms($sectors_id, $org_id){
        if(!empty($sectors_id) && !empty($org_id)){
            $arr_to_insert = [];
            $ids = [];
            foreach($sectors_id as $sector_id){
                $ids[] = self::updateOrCreate(['sector_id' => $sector_id, 'organization_id' => $org_id, 'fictive' => true], [ 'status' => 2, 'name' => 'fictive'])->id;
            }
            ProgramFormat::setFictive($ids);
        }
    }

    public function scopeFictive($query) {
        return $query->where('fictive', true);
    }

    public function scopeNotFictive($query){
        return $query->where('fictive', false)->orWhereNull('fictive');
    }


// ================================= Fictive ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


    public function scopeUnread($query){
        return $query->where('status', 1);
    }

    public function scopeParameters($query, $params = []){
        return $query->whereHas('parameters', function($query) use ($params){
           $query->whereIn('parameter_id', $params)->where('value', 1);
        }, '=', count($params));
    }

    public function scopeSearchByPhrase($query, $phrase = ''){
        return $query->where('name', 'ILIKE', "%{$phrase}%")->orderByRaw("name ILIKE '{$phrase}%' asc");
    }

    public function scopeNotDel($query){
        return $query->whereNull('deleted_at');
    }

    public function scopeAllowed($q){
        return $q->where(function($query){
            $query->whereHas('organization', function($orgQ) {
                return $orgQ->withOutModerate()->my();
            })->orWhereNull('organization_id');
        });
    }

    public function scopeSearch($query, $ids = []){
        if(!empty($ids)) $query = $query->whereIn('sector_id', $ids);
        return $query->notFictive();
    }

//    public static function getLimits($orgQuery, $ids){
//        $programQuery = self::search($ids)->whereHas('organization', function($query) use ($orgQuery){
//            return $query->whereSub($orgQuery);
//        });
//        return ProgramFormat::getLimits($programQuery);
//    }

    public function createNotify(){
        if(empty($this->organization->user_id)) return;
        if($this->isOriginal() && $this->wasChanged('status') && $this->status != 1){
            $this->notification()->create(['user_id' => $this->organization->user_id, 'type' => 12, 'text' => $this->status]);
        }
        elseif($this->isDuplicate() && $this->wasChanged('status') && $this->status == 3){
            $original = $this->getOriginalModel();
            $original->notification()->create(['user_id' => $original->organization->user_id, 'type' => 14, 'text' => '3']);
        }
        elseif($this->isOriginal() && $this->movingDuplicate){
            $this->notification()->create(['user_id' => $this->organization->user_id, 'type' => 13, 'text' => '2']);
        }

    }



}
//'scope' => 'NotChild'
