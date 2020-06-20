<?php namespace Cds\Study\Models;

use Model;
use October\Rain\Exception\ValidationException;
use System\Models\File;
use Illuminate\Filesystem\Filesystem;
use \Cds\Study\Models\CdsFile;

/**
 * ContactPerson Model
 */
class ContactPerson extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    const DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];
    const DAYS_TRANSLATE = [
        'monday'    => 'Пн',
        'tuesday'   => 'Вт',
        'wednesday' => 'Ср',
        'thursday'  => 'Чт',
        'friday'    => 'Пт',
        'saturday'  => 'Сб',
        'sunday'    => 'Вс',
    ];

    public $CONTACTS = [
        'telegram' => 'Телеграм',
        'viber' => 'Viber',
        'facebook' => 'Facebook',
        'vkontakte' => 'Вконтакте',
        'whatsapp' => 'Watsapp'
    ];

    public $CONTACT_LINKS = [
        'telegram' => 'https://tlgg.ru/',
        'viber' => 'viber://chat?number=+',
        'facebook' => 'https://www.facebook.com/',
        'vkontakte' => 'https://vk.com/',
        'whatsapp' => 'whatsapp://send?phone=+',

    ];

    /**
     * @var string The database table used by the model.
     */
    public $table = 'cds_study_contact_people';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [ 'original_id', 'name', 'last_name', 'middle_name', 'status', 'position', 'phone', 'email', 'is_public', 'order', 'organization_id', 'is_general', 'avatar', 'schedule', 'params'];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required|alpha',
        'last_name' => 'required|alpha',
        'middle_name' => 'required|alpha',
        'position' => 'required',
        'email' => 'nullable|email',
        'organization_id' => 'nullable|integer',
        'order' => 'required|integer',
        'avatar' => 'nullable|max:2048|mimes:jpeg,bmp,png,jpg'
    ];

    public $customMessages = [
        'name.required' => 'Имя обязательно',
        'name.alpha' => 'Имя должно состоять только из букв',
        'last_name.required' => 'Фамилия обязательна',
        'last_name.alpha' => 'Фамилия должно состоять только из букв',
        'middle_name.required' => 'Отчество обязательно',
        'middle_name.alpha' => 'Отчество должно состоять только из букв',
        'position.required' => 'Должность обязательна',
        'email.email' => 'E-mail указан неверно',
        'organization_id.required' => 'ID организации обязателен',
        'organization_id.integer' => 'ID организации должен быть числом',
        'order.required' => 'Сортировка обязателена',
        'order.integer' => 'Сортировка должна быть числом',
        'avatar.required' => 'Аватар обязателен',
        'avatar.max' => 'Картинка не должна превышать 2 мб',
        'avatar.mimes' => 'У аватара могут быть расширения только jpeg bmp png jpg'
    ];

    public $attributes = [
        'is_public' =>false,
        'is_general' =>false,
        'order' => 500,
        'status' => 1
    ];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [
        'is_public' => 'boolean',
        'is_general' => 'boolean',
        'organization_id' => 'integer'
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = ['params'];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = ['organization_id'];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at'
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
        'organization' => [Organization::class],
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
        'avatar' => [CdsFile::class]
    ];
    public $attachMany = [];



    public function afterValidate()
    {
        if($this->attributes['is_general'])
        {
            $this->where('organization_id', $this->attributes['organization_id'])->where('is_general', true)->update(['is_general' => false]);
        }

    }

    public function beforeUpdate(){
    }

    public function setOrganizationIdAttribute($value) {
        $this->attributes['organization_id'] = (empty($value) ? null : $value);
    }

    public function getStatusOptions($keyValue = null){
        return $this->statuses;
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


    protected $dirtyFields = ['name', 'last_name', 'middle_name', 'position', 'email'];
    protected $dirtyRelationsAttachOne = ['avatar'];
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
            return $query->whereNull('cds_study_contact_people.original_id');
        });

        self::addGlobalScope('isActiveStatus', function($query){
            return $query->whereNotIn('cds_study_contact_people.status', self::UPDATE_STATUSES);
        });
    }

    // ============================= Duplicate ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


    // ============================= Move Duplicate ================================= //
    //==========================================================================================//

    protected $key = 'contact_person_id';
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

    public function scopeByOrganization($query, $org_id)
    {
        return $query->where('organization_id', $org_id)->orderBy('order', 'asc');
    }

    public function scopeAllowed($q){
        return $q->where(function($query){
            $query->whereHas('organization', function($orgQ) {
                return $orgQ->withOutModerate()->my();
            })->orWhereNull('organization_id');
        });
    }

    public function getFullNameAttribute()
    {
        return ucwords("{$this->last_name} {$this->name} {$this->middle_name}");
    }

    public function getAvatar()
    {
        if(!empty($this->avatar)) $avatar = $this->avatar->getThumb(250, 250, ['mode' => 'crop']);
        else $avatar = 'https://picsum.photos/50/50';
        return $avatar;
    }

    public function setParamsAttribute($value){
        if(is_string($value)) $params = json_decode($value, true);
        else $params= $value;
        foreach(self::DAYS as $day){
            $param = $params[$day];
            if(empty($param['start']) || empty($param['end'])) $params[$day]['weekend'] = true;
            else $params[$day]['weekend'] = false;
            if(empty($param['brake_start']) || empty($param['brake_end'])) $params[$day]['brake'] = false;
            else $params[$day]['brake'] = true;

        };
        $this->attributes['params'] = json_encode($params);
        $this->attributes['schedule'] = $this->getScheduleText($params);
    }

    public function getScheduleText($params = []){

        $work_days = [];
        $weekends = [];
        $start = '';
        $end = '';
        $start_date = '';
        $end_date = '';
        $brake = [];
        $brake_start = '';
        $brake_end = '';
        $brake_start_date = '';
        $brake_end_date = '';

        foreach(self::DAYS as $day){
            $schedule = $params[$day];
            if(!empty($schedule['weekend'])){
                $weekends[] = self::DAYS_TRANSLATE[$day];
                if(!empty($start)){
                    if(!empty($end)) $work_days[] = "{$start} - {$end}: {$start_date} - {$end_date}";
                    else $work_days[] = "{$start}: {$start_date} - {$end_date}";
                }
                $start = '';
                $end = '';
                $start_date = '';
                $end_date = '';
                if(!empty($brake_start)){
                    if(!empty($brake_end)) $brake[] = "{$brake_start} - {$brake_end}: {$brake_start_date} - {$brake_end_date}";
                    else $brake[] = "{$brake_start}: {$brake_start_date} - {$brake_end_date}";
                }
                $brake_start = '';
                $brake_end = '';
                $brake_start_date = '';
                $brake_end_date = '';
            } else {
                if(empty($start)){
                    $start = self::DAYS_TRANSLATE[$day];
                    $start_date = $schedule['start'];
                    $end_date = $schedule['end'];
                } else {
                    if($start_date == $schedule['start'] && $end_date == $schedule['end']) $end = mb_strtolower(self::DAYS_TRANSLATE[$day], 'UTF-8');
                    else {
                        if(!empty($end)) $work_days[] = "{$start} - {$end}: {$start_date} - {$end_date}";
                        else {
                            $work_days[] = "{$start}: {$start_date} - {$end_date}";
                        }
                        $start = self::DAYS_TRANSLATE[$day];
                        $end = '';
                        $start_date = $schedule['start'];
                        $end_date = $schedule['end'];
                    }
                }

                if(empty($brake_start) && !empty($schedule['brake'])){
                    $brake_start = self::DAYS_TRANSLATE[$day];
                    $brake_start_date = $schedule['brake_start'];
                    $brake_end_date = $schedule['brake_end'];
                } elseif(!empty($brake_start) && !empty($schedule['brake'])) {
                    if($brake_start_date == $schedule['brake_start'] && $brake_end_date == $schedule['brake_end']) $brake_end = mb_strtolower(self::DAYS_TRANSLATE[$day], 'UTF-8');
                    else {
                        if(!empty($brake_end)) $brake[] = "{$brake_start} - {$brake_end}: {$brake_start_date} - {$brake_end_date}";
                        else $brake[] = "{$brake_start}: {$brake_start_date} - {$brake_end_date}";
                        $brake_start = self::DAYS_TRANSLATE[$day];
                        $brake_end = '';
                        $brake_start_date = $schedule['brake_start'];
                        $brake_end_date = $schedule['brake_end'];
                    }
                } elseif(!empty($brake_start) && empty($schedule['brake'])){
                    if(!empty($brake_end)) $brake[] = "{$brake_start} - {$brake_end}: {$brake_start_date} - {$brake_end_date}";
                    else $brake[] = "{$brake_start}: {$brake_start_date} - {$brake_end_date}";
                    $brake_start = '';
                    $brake_end = '';
                    $brake_start_date = '';
                    $brake_end_date = '';
                }

            }


        }
        $work_days = implode(', ', $work_days);

        if (!empty($work_days) && (!empty($brake) || !empty($weekends))) $work_days .= ", <br>";
        if (!empty($brake)){
            $brake = implode(', ', $brake);
            $brake = 'Перерыв: ' . $brake;
            if(!empty($weekends)) $brake .= ", <br>";
        }else $brake = '';
        $weekends = implode(', ', $weekends);
        if (!empty($weekends)) $weekends .= ': выходной';

        return $work_days . $brake . $weekends;
    }

    public function getContactLink($code = '', $value = ''){

        return !empty($this->CONTACT_LINKS[$code]) ? $this->CONTACT_LINKS[$code] . $value : null;
    }
}
