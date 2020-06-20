<?php namespace Cds\Study\Models;

use Model;
use Cds\Study\Models\Program;

/**
 * Promocode Model
 */
class Promocode extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'cds_study_promocodes';

    const CODE = [
        'guest' => 0,
        'user' => 1
    ];

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['value', 'type', 'promocode', 'organization_id', 'active'];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'value' => 'required|integer',
        'type' => 'required|integer',
        'promocode' => 'required|numeric',
        'organization_id' => 'required|integer|exists:cds_study_programs,id'
    ];

    public $customMessages = [
        'value.required' => 'Значение обязательно',
        'value.integer' => 'Значение должно быть числом',
        'type.required' => 'Тип обязателен',
        'type.integer' => 'Не верно указан тип',
        'promocode.required' => 'Промокод обязателен',
        'promocode.numeric' => 'Промокод может быть только из цифр',
//        'promocode.digits_between' => 'Промокод должен состоять из 7 цифр',
        'organization_id.required' => 'Не указана организация',
        'organization_id.integer' => 'Не правильно введена организация',
        'organization_id.exists' => 'Организации не существует',
    ];

    public $attributes = [
        'value' => 0,
        'active' => false
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
        'updated_at'
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [
        'program' => [Program::class]
    ];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public static function generatePromocodes()
    {
        $guest= '';
        $user = '';
        while($guest === $user)
        {
            $guest = rand(1000000, 9999999);
            $user = rand(1000000, 9999999);
        }

        return ['guest' => ['promocode' => $guest], 'user' => ['promocode' => $user]];
    }

    public function scopeByUsers($query)
    {
        return $query->where('type', 1);
    }

    public function scopeByGuests($query)
    {
        return $query->where('type', 0);
    }

    public function scopeValue($query)
    {
        return $query->where('value', '>', 0);
    }

    public function view(){
        UserAction::createViews(['object_type' => self::class, 'object_id' => $this->id]);
        UserAction::createViewsPromocode(['object_type' => Organization::class, 'object_id' => $this->organization_id]);
    }

    public static function createPromocode($value='', $promocode='', $organization_id=null, $type=0){
        self::create([
            'value' => $value,
            'promocode' => $promocode,
            'type' => $type,
            'organization_id' => $organization_id
        ]);
    }

    public static function createGuestPromocode($value='', $promocode='', $organization_id=null){
        self::createPromocode($value, $promocode, $organization_id, 0);
    }

    public static function createUserPromocode($value='', $promocode='', $organization_id=null){
        self::createPromocode($value, $promocode, $organization_id, 1);
    }

}
