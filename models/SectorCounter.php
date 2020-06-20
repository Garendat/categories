<?php namespace Cds\Study\Models;

use Model;
use Cds\Study\Models\Sector;
use Cds\Study\Models\Realm;
use Session;

/**
 * SectorCounter Model
 */
class SectorCounter extends Model
{
    use \October\Rain\Database\Traits\Validation;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'cds_study_sector_counters';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['sector_id', 'realm_id', 'org_count', 'program_count'];

    /**
     * @var array Validation rules for attributes
     */

    public $rules = [
        'realm_id' => 'required|integer',
        'sector_id' => 'required|integer',
        'org_count' => 'nullable|integer',
        'program_count' => 'nullable|integer'
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
        'sector' => Sector::class,
        'realm' => Realm::class
    ];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public function scopeByCity($query){
        $city_id = Session::get('realm.id');
        return $query->where('realm_id', $city_id);
    }
}
