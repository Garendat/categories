<?php namespace Cds\Study\Models;

use Carbon\Carbon;
use Model;
use October\Rain\Database\QueryBuilder;
use Cds\Study\Models\CdsFile;

/**
 * Events Model
 */
class Event extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Nullable;
    use \October\Rain\Database\Traits\SoftDelete;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'cds_study_events';

    protected $dates = ['created_at', 'updated_at', 'start_at', 'end_at', 'deleted_at'];
    protected $nullable = ['body', 'user_id', 'start_at', 'end_at', 'city_id'];

    public $attributes = [
        //'title' => 'Новое мероприятие',
        'status' => 1,
    ];

    public $attributesNames = [
        'title' => 'Название',
    ];

    public $appends = [
        //'baseId',
    ];
    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'start_at',
        'end_at',
        'published',
        'title',
        'body',
        'image',
        'organization_id',
        'original_id',
        'city',
        'city_id',
        'place',
    ];

    protected $rules = [
//        'organization_id' => 'numeric|exists:cds_study_organizations,id'
    ];

    protected $customMessages = [
        'organization_id' => 'Организация',
    ];


    /**
     * @var array Relations
     */
    public $hasOne = [
        'duplicate' => [
            self::class,
            'key' => 'original_id',
            'otherKey' => 'id',
            'scope' => 'withOutModerate'
        ]
    ];
    public $hasMany = [];
    public $belongsTo = [
        'organization' => [ Organization::class,
            'scope' => 'withOutModerate',
        ],
        'user' => ['Rainlab\User\Models\User'],
        'city' => [ Realm::class,
            'key' => 'city_id'
        ],
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
    public $attachOne = [
        'image' => [CdsFile::class]
    ];
    public $attachMany = [];

    // ============================= Before After Events ============================= //

    // ============================= Getters Setters ================================= //

    public function setOrganizationIdAttribute($value) {
        $this->attributes['organization_id'] = (empty($value) ? null : $value);
    }

    public function setStartAtAttribute($value)
    {   
        if((\App::runningInBackend())) {
            $this->attributes['start_at'] = $value;
        } else {
            $this->attributes['start_at'] = $this->checkDate($value);
        }
        
    }

    public function setEndAtAttribute($value)
    {
        if((\App::runningInBackend())) {
            $this->attributes['end_at'] = $value;
        } else {
            $this->attributes['end_at'] = $this->checkDate($value);
        }
    }

    public function getStatusOptions($keyValue = null){
        return $this->statuses;
    }

    public function getTerm() {
        $term = '';
        if ($this->start_at)
            $term .= $this->start_at->format('d.m.Y h:i');
        if ($this->end_at)
            $term .= $this->end_at->format(' - d.m.Y h:i');
        return $term;
    }

    // ============================= Scopes filter =================================== //

    /**
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    public function scopeIsPublished($queryBuilder)
    {
        return $queryBuilder->where('published', true);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return QueryBuilder
     */
    public function scopeNotPublished(QueryBuilder $queryBuilder): QueryBuilder
    {
        return $queryBuilder->where('published', false);
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $date
     * @return QueryBuilder
     */
    public function scopeGetByData(QueryBuilder $queryBuilder, string $date): QueryBuilder
    {
        return $queryBuilder->where('start_date', '>=', $date)->where('end_date','<=', $date);
    }

    // ============================= Make Scopes ================================= //
    // ============================= Public Methods ================================= //


    public function scopeAllowed($q){
        return $q->where(function($query){
            $query->whereHas('organization', function($orgQ) {
                return $orgQ->withOutModerate()->my();
            })->orWhereNull('organization_id');
        });
    }

    public function scopeByOrganization($query, $org_id)
    {
        return $query->where('organization_id', $org_id);
    }


    // ============================= Protected Methods ================================= //

    public function scopeBetweenDate($query){
        $today = Carbon::today();
        return $query->whereDate('start_at', '>=', $today)->whereDate('end_at','<=', $today);
    }



    // ============================= Duplicate ================================= //
    //==========================================================================================//
    public $statuses = [
        0 => '<не задан>',
        1 => 'Ожидает подтверждения',
        2 => 'Активна',
        3 => 'Отклонено'
    ];

    const UPDATE_STATUSES = [1, 3];

    protected $dirtyFields = [
        'title',
        'body',
        'place',
    ];

    protected $dirtyRelationsAttachOne = ['image'];
    protected $dirtyRelationsHasMany = [];
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
            return $query->whereNull('cds_study_events.original_id');
        });

        self::addGlobalScope('isActiveStatus', function($query){
            return $query->whereNotIn('cds_study_events.status', self::UPDATE_STATUSES);
        });
    }

    // ============================= Duplicate ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


    // ============================= Move Duplicate ================================= //
    //==========================================================================================//

    protected $key = 'event_id';
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

    private function checkDate($value)
    {
        //Должны ввести на форму в формате 01.01.1999, а положится в базу 1999-01-01
        if (!empty($value)) {
            try {
                return Carbon::createFromFormat('d.m.Y', $value)->format("Y-m-d");
            } catch (\Exception $e) {
                throw new \ValidationException(['date' => 'Дата выдачи должна соответствовать формату День.Месяц.Год (31.12.1999)']);
            }
        } else {
            return null;
        }
    }
}
