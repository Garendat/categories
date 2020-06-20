<?php namespace Cds\Study\Models;

use Carbon\Carbon;
use Model;
use Cds\Study\Models\Program;
use RainLab\User\Facades\Auth;
use System\Models\File;

/**
 * ProgramFormat Model
 */
class ProgramFormat extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string The database table used by the model.
     */
    const TERM_TYPES = [
        'h' => 'ч.',
        'd' => 'дн.',
        'm' => 'мес.',
        'y' => 'г.',
        'l' => 'л.'
    ];

    const NAMES = [
        'internal' => 'Очное',
        'extramural' => 'Заочное',
        'internal_extramural' => 'Очно-заочное',
        'distance' => 'Дистанционное',
    ];

    const NAME_CODES = [
        'internal',
        'extramural',
        'internal_extramural',
        'distance',
    ];
    const START_TEXT_LIST = [
        1 => 'По мере набора',
    ];

    public $table = 'cds_study_program_formats';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];
    public $attributes = [
        'price_legal' => 0,
        'price_person' => 0,
        'term_to_sec' => 0,
        'term_value' => 0,
        'nds' => false,
        'start_date' => null,
        'fictive' => false,
        'status' => 1
    ];
    /**
     * @var array Fillable fields
     */
    protected $fillable = ['name', 'price_legal', 'price_person', 'start_option', 'term_type', 'term_value', 'term_to_sec', 'start_date',
        'description', 'program_id', 'code', 'nds', 'fictive', 'original_id', 'status'];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'code' => 'required',
        'price_person' => 'required|numeric',
        'price_legal' => 'required|numeric',
        'start_option' => 'nullable',
        'term_type' => 'required|alpha',
        'term_value' => 'required|numeric',
        'start_date' => 'nullable|date|required_without:start_option',
        'description' => 'nullable',
//        'program_id' => 'required|integer|exists:cds_study_programs,id',
        'nds' => 'nullable'
    ];

    public $customMessages = [
        'code.required' => 'Имя формы обучения обязательно',
        'price_person.required' => 'Цена для физических лиц обязательна',
        'price_person.numeric' => 'Не правильно указан формат цены для физических лиц',
        'price_legal.required' => 'Цена для юридических лиц обязательна',
        'price_legal.numeric' => 'Не правильно указан формат цены для юридических лиц',
        'term_value.required' => 'Поле "Срок обучения" обязательно для заполнения',
        'term_value.numeric' => 'Поле "Срок обучения" должно быть числом',
        'term_type.required' => 'Не указан тип даты срока обучения',
        'term_type.alpha' => 'Тип даты срока обучения указа не правильно',
        'start_date.required_without' => 'Не указано начало обучения',
        'start_date.date' => 'Не верный формат поля "Начало обучения"',
        'program_id.required' => 'Не указана программа',
        'program_id.integer' => 'Не верный формат программы',
        'program_id.exists' => 'Такой программы не существует',
        'nds.boolean' => 'Не верный формат НДС',
    ];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'start_date'
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [
        'duplicate' => [
            self::class,
            'scope' => 'withOutModerate',
            'key' => 'original_id',
            'otherKey' => 'id'
        ]
    ];
    public $hasMany = [];
    public $belongsTo = [
        'program' => [Program::class],
        'original' => [
            self::class,
            'otherKey' => 'id',
            'key' => 'original_id'
        ]

    ];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];


// ================================= Events This Model ==================================== //
//==========================================================================================//

    public function beforeSave()
    {
        if(empty($this->id) || $this->original['term_type'] !== $this->attributes['term_type']
            || $this->original['term_value'] !== $this->attributes['term_value'])
        {
            $this->setTermToSec();
        }
    }
// ================================= Events This Model ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



// ============================= Getters Setters This models ================================= //
//==========================================================================================//

    protected function setTermToSec()
    {
        switch($this->term_type)
        {
            case 'h':
                $this->term_to_sec = $this->term_value * 3600;
                break;
            case 'd':
                $this->term_to_sec = $this->term_value *  24 * 3600;
                break;
            case 'm':
                $this->term_to_sec = $this->term_value * 30  *  24 * 3600;
                break;
            case 'y' or 'l':
                $this->term_to_sec = $this->term_value * 365 * 24 * 3600;
                break;
        }
    }

    public function getTermViewAttribute()
    {
        $value = $this->term_value;
        $term_type = self::TERM_TYPES[$this->term_type];
        if($this->term_type == 'm' && $this->term_value > 12){
            $value /= 12;
            $term_type = "г.";
        }
        return "{$this->term_value} {$term_type}";
    }

    public function getNameAttribute()
    {
        return self::NAMES[$this->code];
    }

    public function getCodeOptions(){
        return self::NAMES;
    }

    public function getTermTypeOptions(){
        return self::TERM_TYPES;
    }

    public function getStartAttribute()
    {
        if(!empty($this->start_option))
            return self::START_TEXT_LIST[$this->start_option];
        elseif(!empty($this->start_date))
            return $this->start_date->format('d.m.Y');
        else {
            return "Не указано";
        }
    }
    public function getDateInceptionAttribute()
    {
        return !empty($this->start_date) ? $this->start_date->format('d.m.Y') : null;
    }

    public function getCurrentPriceAttribute()
    {
        if($user = Auth::getUser())
        {
            if(!empty($user->type == 2)) return $this->price_legal;
        }
        return $this->price_person;
    }

    public function setStartDateAttribute($value){
        if(!\App::runningInBackend()){
            if(empty($value)) $this->attributes['start_date'] = null;
             else
                 $this->attributes['start_date'] = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');
        } else {
            $this->attributes['start_date'] = $value;
        }
    }
// ============================= Getters Setters This models ================================= //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



// ================================= Fictive ==================================== //
//==========================================================================================//

    public static function setFictive($programs_ids){
        foreach($programs_ids as $id){
            self::updateOrCreate(['fictive' => true, 'program_id' => $id],
                ['code' => 'internal', 'price_person' => 0,
                    'price_legal' => 0, 'start_option' => true,
                    'term_type' => 'm', 'term_value' => 1, 'status' => 2]);
        }
    }

// ================================= Fictive ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


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

    public $dirtyFields = ['description'];

    protected $dirtyRelationsAttachOne = [];
    protected $dirtyRelationsHasMany = [];
    protected $dirtyRelationsHasOne = [];
    protected $dirtyRelationsAttachMany = [];
    public $sessionKey = null;

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
            return $query->whereNull('cds_study_program_formats.original_id');
        });

        self::addGlobalScope('isActiveStatus', function($query){
            return $query->whereNotIn('cds_study_program_formats.status', self::UPDATE_STATUSES);
        });
    }

    // ============================= Duplicate ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


    // ============================= Move Duplicate ================================= //
    //==========================================================================================//

    protected $key = 'program_format_id';
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

//if($user = Auth::getUser())
//{
//if(!empty($user->type == 2)) return $this->price_legal;
//}
//return $this->price_person;


    public function scopeByMinPrice($query, $price = null){
        return $query->byPrice($price);
    }

    public function scopeByPrice($query, $price = null, $operator = '>='){
        return $query->when(!empty($price), function($query) use ($price, $operator){
            $user = Auth::getUser();
            if((!empty($user->type)) && $user->type == 2) $query = $query->where('price_legal', $operator, $price);
            else $query = $query->where('price_person', $operator, $price);
            return $query;
        });
    }

    public function scopeByMaxPrice($query, $price = null){
        return $query->byPrice($price, '<=');
    }

    public function scopeByTerm($query, $term = null, $operator = '>='){
        return $query->when(!empty($term), function($query) use ($term, $operator){
            return $query = $query->where('term_to_sec', $operator, $term);
        });
    }

    public function scopeByMinTerm($query, $term = null){
        return $query->byTerm($term);
    }

    public function scopeByMaxTerm($query, $term = null){
        return $query->byTerm($term, '<=');
    }

    public function scopeNotFictive($query){
        return $query->where('fictive', false)->orWhereNull('fictive');
    }

    public function scopeFilter($query, $parameters){
        return $query
            ->byMaxPrice($parameters['max_price'])
            ->byMinPrice($parameters['min_price'])
            ->byMinTerm($parameters['min_term'])
            ->byMaxTerm($parameters['max_term']);
    }

    public static function getLimits($search = []){

        $query = self::whereHas('program', function($query) use ($search){
           return $query->search($search['ids'])->orWhereHas('organization', function($query) use ($search){
               return $query->when(!empty($search['phrases']), function($query) use($search){
                   return $query->wherePhrases($search['phrases']);
               })->when(!empty($search['organization_id']), function($query) use($search){
                   return $query->where('id', $search['organization_id']);
               });
           });
        });
        $user = Auth::getUser();
        $price_column = (!empty($user->type) && $user->type == 2) ? 'price_legal' : 'price_person';
        $query->selectRaw("min(term_to_sec) as min_term, max(term_to_sec) as max_term, min({$price_column}) as min_price, max({$price_column}) as max_price");
        return ($query->first()) ?? '';
    }

//$q = self::whereHas('program', function($query) use ($programQuery){
//            return $query->whereSub($programQuery);
//        })->selectRaw("min(term_to_sec) as min_term, max(term_to_sec) as max_term, min({$price_column}) as min_price, max({$price_column}) as max_price");
}
