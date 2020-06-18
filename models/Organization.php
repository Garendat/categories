<?php namespace Cds\Study\Models;

use Carbon\Carbon;
use Cds\Study\Models\UserAction;
use Illuminate\Support\Facades\Lang;
use Model;
use October\Rain\Database\Builder;
use October\Rain\Database\Relations\BelongsToMany;
use Cds\Study\Models\User;
use Cds\Study\Models\Program;
use Auth;
use System\Models\File;
use Session;
use Db;


/**
 * Model
 */
class Organization extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \October\Rain\Database\Traits\Nullable;

    // если модель уже прошла модерацию и статус отличный от 0 то мы запрещаем вносить поля пользователям
    const ADDITIONAL_GUARDED = [
        'name',
        'inn',
        'foundation_date'
    ];

    const ORG_FORM = [
        'ООО',
        'АО',
        'ИП'
    ];

    public $organization_types = [
        'Государственная',
        'Частная',
        'Не определен'
    ];
    /**
     * @var string The database table used by the model.
     */
    public $table = 'cds_study_organizations';

    public $jsonable = ['info'];

    public $nullable = ['info'];

    public $guarded = ['*'];

    public $appends = [];

    public $addFictive = false;

    protected $fillable = [
        'is_general', 'name', 'realm_id', 'tariff_id', 'user_id', 'status', 'site_url',
        'description', 'address', 'inn', 'region', 'metro',  'foundation_date', 'type', 'logotype', 'price', 'fictive', 'email', 'phone', 'original_id',
        'events', 'stocks', 'images', 'contact_people', 'certificates', 'info',
    ];

    public $dates = ['created_at', 'updated_at', 'foundation_date', 'deleted_at'];
    public $attributes = [
        'is_general' => false,
        'type' => 0,
        'status' => 1,
    ];

    public $casts = [
        'is_general' => 'boolean',
        'type' => 'integer',
        'status' => 'integer',
    ];

    /**
     * @var array Validation rules
     */
    public $rules = [
        'name' => 'required',
        'inn' => 'required|numeric|digits_between:10,12',
        'description' => 'nullable',
        'status' => 'required|numeric',
        'address' => 'required',
        'region' => 'nullable',
        'metro' => 'nullable',
        'foundation_date' => 'nullable|date',
        'type' => 'required|between:0,1',
        'realm_id' => 'nullable|exists:cds_study_realms,id',
        'tariff_id' => 'nullable|exists:cds_study_tariffs,id',
        'user_id' => 'nullable|exists:users,id',
        'logotype' => 'nullable|max:2048|mimes:jpeg,bmp,png,jpg',
        'price' => 'nullable|max:15360|mimes:doc,pdf,docx',
//        'email' => 'nullable|email',
        'phone' => 'nullable|max:11',
        'site_url' => 'nullable|url|active_url'
    ];

    public $attributeNames = [
        'name' => 'Наименование',
        'inn' => 'ИНН',
        'description' => 'Описание',
        'status' => 'Статус',
        'address' => 'Адрес',
        'foundation_date' => 'Дата основания',
        'type' => 'Тип',
        'logotype' => 'Логотип',
        'email' => 'E-mail',
        'phone' => 'Телефон',
        'site_url' => 'Сайт',
    ];

    public $customMessages = [
        'name.required' => 'Название обязательно',
        'inn.required' => 'Поле ИНН обязательно для заполнения',
        'inn.numeric' => 'ИНН может содержать только цифры',
        'inn.between' => 'ИНН может содержать 10 цифр для Юридического лица и 12 цифр для физического',
        'status.required' => 'Статус обязателен',
        'address.required' => 'Адрес обязателен для заполнения',
        'region.required' => 'Регион обязателен для заполнения',
        'foundation_date.required' => 'Дата основания обязательна',
        'foundation_date.date' => 'Дата основания должна быть в корректной форме',
        'realm_id.exists' => 'Такого домена не существует',
        'tariff_id.exists' => 'Такого тарифа не существует',
        'user_id.exists' => 'Такого пользователя не существует',
        'logotype.required' => 'Логотип обязателен',
        'logotype.max' => 'Логотип не должен превышать 2 мб',
        'logotype.mimes' => 'У логотипа могут быть расширения только jpeg bmp png jpg',
        'price.max' => 'Размер прайса не должен превышать 2 мб',
        'price.mimes' => 'Допустимые расширения doc pdf',
        'email.email' => 'E-mail указан неверно',
        'site_url.url' => 'Ссылка не является адресом',
        'site_url.active_url' => 'Адрес сайта должен быть активен'
    ];

    public $belongsTo = [
        'realm' => [Realm::class],
        'tariff' => [Tariff::class],
        'user' => [User::class, 'key' => 'user_id'],
        'original' => [
            self::class,
            'otherKey' => 'id',
            'key' => 'original_id'
        ]
    ];

    public $belongsToMany = [
        'sectors' => [
            'table' => 'cds_study_programs',
            Sector::class,
            'conditions' => 'deleted_at is null',
            'pivot' => ['name'],
            ],
        'active_services' => [Service::class,
            'table' => 'cds_study_object_services',
            'key' => 'object_id',
            'conditions' => "object_type = 'Cds\Study\Models\Organization' AND CURRENT_DATE >= date_start AND CURRENT_DATE <= date_end",
            'pivot' => ['date_end', 'date_start', 'id'],
        ],

    ];


    public $hasMany = [
        'programs' => [ Program::class,
            'softDelete' => true,
        ],
        'events' => [ Event::class,
            'softDelete' => true,
        ],
        'stocks' => [ Stock::class,
            'order' => 'order asc',
            'softDelete' => true,
        ],
        'certificates' => [ Certificate::class,
            'order' => 'order asc',
            'softDelete' => true,
        ],
        'contact_people' => [ ContactPerson::class,
            'order' => 'order asc',
            'softDelete' => true,
        ],
        'parameters' => [ ObjectValue::class,
            'key' => 'object_id',
            'otherKey' => 'id',
            'scope' => 'byOrganizations'
        ],
        'unread_programs' => [ Program::class,
            'scope' => 'getDirtyForController',
        ],
    ];

    public $hasOne = [
        'general_contact_person' => [ ContactPerson::class,
            'conditions' => 'is_general = true',
            'softDelete' => true
        ],
        'promocode_user' => [ Promocode::class,
            'softDelete' => true,
            'scope' => 'byUsers'
        ],
        'promocode_guest' => [ Promocode::class,
            'softDelete' => true,
            'scope' => 'byGuests'
        ],
        'duplicate' => [
            self::class,
            'scope' => 'withOutModerate',
            'key' => 'original_id',
            'otherKey' => 'id',

        ]
    ];

    public $morphOne = [
        'notification' => [ Notification::class,
            'name' => 'object',
        ],
        'my_rate' => [ UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'rate'",
            'scope' => 'my'
        ],
        'my_favorite' => [ UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'favorite'",
            'scope' => 'my'
        ],
    ];
    public $morphMany = [
        'bills' => [ Bill::class,
            'name' => 'object',
        ],
        'services' => [ ObjectService::class,
            'name' => 'object',
        ],
        'rates' => [ UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'rate'"
        ],
        'views' => [ UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'view'"
        ],
        'comments' => [ Comment::class,
            'name' => 'object',
            'scope' => 'active'
        ],
        'notifications' => [ Notification::class,
            'name' => 'object',
        ],
        'requests' => [ UserAction::class,
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
        'site_clicks' => [
            UserAction::class,
            'name' => 'object',
            'conditions' => "action = 'site_url'"
        ],
        'actions' => [
            UserAction::class,
            'name' => 'object',
        ],
    ];

    public $attachOne = [
        'logotype' => [CdsFile::class],
        'price' => [CdsFile::class],
    ];

    public $attachMany = [
        'images' => [ CdsFile::class,
            'delete' => true
        ],
    ];

    // ================================= Events This Model ==================================== //
    //==========================================================================================//

    public function beforeCreate(){
//        $this->setFoundationDate();
    }

//    public function afterCreate(){
//        if(empty($this->attributes['id'])){
//            $this->programs()->create(['name' => 'Тест', ]);
//        }
//    }


    public function afterSave(){
        //отправляем уведомление автору статьи, если подтвердили новую организацию
        $this->createNotify();

        //уведомляем пользователя, что на него назначили организацию
        if ( !empty($this->user_id) && $this->wasChanged('user_id') ) {
            $data = ['user_id' => $this->user_id, 'type' => 4];
            $this->notification()->create($data);
        }
        $this->setFictivePrograms();
    }

    // Запрещаем пользователю менять поля, если статус изменен
//    public function fill($attributes){
//        if(!empty($this->original['id']) && $this->original['status'] !== 0 && !(\App::runningInBackend()))
//            $this->fillable = array_diff($this->fillable, self::ADDITIONAL_GUARDED);
//
//        $res = parent::fill($attributes);
//        ///if (!empty($attributes['images'])) dd($attributes, $res);
//        return $res;
//    }

// ================================= Events This Model ==================================== //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//





    // ============================= Getters Setters This models ================================= //
    //==========================================================================================//

    public function getPhoneViewAttribute(){
        if(!empty($this->phone)){
            return  "+{$this->phone}";
        }
    }

    public function setPhoneAttribute($value){
        if(!empty($value)){
            $this->attributes['phone'] = preg_replace("/[^0-9]/", '', $value);
        }
    }


    public function setFoundationDateAttribute($value){
        if((\App::runningInBackend())) $this->attributes['foundation_date'] = $value;
        elseif($value){  $this->attributes['foundation_date'] = Carbon::createFromFormat('d.m.Y', $value)->format('Y-m-d');  }
        elseif(empty($value) && !empty($this->created_at) && empty($this->foundation_date)){
            $this->attributes['foundation_date'] = $this->created_at;
        } else {
            $this->foundation_date = Carbon::now()->format('Y-m-d');
        }
    }

    // Дата основания, но если ее нет то дата создания
    public function getFoundationDateViewAttribute(){
        if(!empty($this->foundation_date))
            return $this->foundation_date->format('d.m.Y');
        elseif(!empty($this->created_at))
            return $this->created_at->format('d.m.Y');
    }
    // Дата основания для личного кабинета
    public function getFoundationDateEditAttribute(){
        if(!empty($this->foundation_date))
            return $this->foundation_date->format('d.m.Y');
        elseif(!empty($this->created_at))
            return $this->created_at->format('d.m.Y');
    }

    // Чтобы выводить статус
    protected function getStatusCodeAttribute(){
        return $this->statuses[$this->attributes['status']];
    }
    // под удаление
    public function getStatusCode(){
        return $this->attributes['status'];
    }

    //Вывод списка статусов для админки
    public function getStatusOptions($keyValue = null){
        return $this->statuses;
    }
    // Вывод Типа организации
    public function getTypeCodeAttribute(){
        return $this->organization_types[$this->attributes['type']];
    }
    // Вывод типа
    public function getTypeCode(){
        return $this->attributes['type'];
    }
    // Вывод списка типов в админку
    public function getTypeOptions(){
        return $this->organization_types;
    }
    // ссылка для редактирования
    public function getEditLinkAttribute(){
        if(!empty($this->original_id)) $id = $this->original_id;
        else $id = !empty($this->attributes['id']) ? $this->attributes['id'] : $this->id;
        return "/lk/organizations/{$id}";
    }
	
	// Тестовая страница для модерации
    public function getLinkTestAttribute(){
        return "/organizations/moderate/{$this->id}";
    }
	
	// основная ссылка
    public function getLinkAttribute(){
        return "/education/organizations/{$this->id}";
    }

    // в самой организации проверяем является ли юзверь владельцем
    public function checkUser(){
        return $this->user_id == Auth::id();
    }
	
	//Ищем город в нашей базе и подвязываем, для поиска по городам
    public function setCityAttribute($value){
        if(!empty($value)){
            if(is_numeric($value))
                $this->attributes['realm_id'] = $value;
            else{
                $city = Realm::byName($value)->first();
                if(empty($city)){
                    trace_log("Город {$value} не найден");
                }
                else{
                    $this->attributes['realm_id'] = $city->id;
                }
            }
        }
    }
	
	// забираем имя города если он есть
    public function getCityAttribute(){
        $realm = $this->realm;
        return (!empty($realm)) ? $realm->name : null;

    }
	
	// проставляем город из нашей базы
    public function setRealmIdAttribute($value){
        $this->attributes['realm_id'] = $value ?: null;
    }


// ============================= Getters Setters This models ================================= //
//!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




// ============================= Fictive ================================= //
//==========================================================================================//

	// Проверяем есть ли нормальные программы, чтобы случайно не добавить фиктивные
    public function existsNotFictivePrograms(){
        return $this->programs()->where(function($query){
            $query->where('fictive', false)->orWhereNull('fictive');
        })->count() > 0;
    }

	// Проверяем есть лм фиктивные программы
    public function existsFictivePrograms(){
        return $this->programs()->where('fictive', true)->count() > 0;
    }

	// Для добавления фиктивных программ в нужные категории и по одному формату для каждой программы
    public function setFictivePrograms() {
        if (empty($this->addFictive))
            $this->programs()->fictive()->with('program_formats')->delete();
        else {
            // получаем все категории послденего уровня вложенные в заданную
            $cats = Sector::getCategoriesByIdAndPhrases('', $this->addFictive);

            // удаляем имющиеся фиктивные программы, которых нет в массиве желаемых, удаляем не через базу чтобы отрабатывали события
            $this->programs()
                ->whereNotIn('sector_id', $cats)
                ->fictive()
                ->get()
                ->each(function($model){
                    $model->delete();
                });

            // создаем требуемые фиктивные программы для этой организации
            Program::setFictivePrograms($cats, $this->id);
        }
    }
	
	// ограничение на фиктивность
    public function scopeFictive($query) {
        return $query->where('fictive', true);
    }
	
	// скоуп на нормальность
    public function scopeNotFictive($query) {
        return $query->where('fictive', false)->orWhereNull('fictive');
    }

    // ============================= Fictive ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




    // ============================= Category ================================= //
    //==========================================================================================//

	// Для отрисовки 2 уровней категорий в формате подсписков в админке и проставление чекбоксов
    public function getCategoriesAttribute(){
        if($this->existsNotFictivePrograms()) return false;
        $cats = Sector::where('lvl', 1)->with('children')->get();
        $ex_cats = $this->sectors->pluck('id')->merge($this->sectors->pluck('parent_id'))->unique()->all();
        return ['cats' => $cats, 'ex_cats' => $ex_cats ];
    }
	
	// сеттер для проставки массива категорий до сохранения, логика выше, которая срабатывает после 
    public function setCategoriesAttribute($value){
        $this->addFictive = $value;
    }

    // ============================= Category ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




    // ============================= Active services Services ================================= //
    //==========================================================================================//

    //действующие тарифные опции у данной организации на заданную дату (если не задано - на текущую)
    public function scopeHasServices($query, $ids = [], $slugs = []){
        return $query->whereHas('services', function($query) use($ids, $slugs){
            $query->active();
            $query->leftJoin('cds_study_services as serv', function ($join) use ($ids, $slugs){
                $join->on('service_id', '=', 'serv.id');
                if(!empty($ids)) $join->whereIn('serv.id', $ids);
                if(!empty($ids)) $join->whereIn('serv.slug', $slugs);
            });
        });
    }

	// отдельная связь в которой прописано условие
    public function scopeWithActiveServicesSlugs($query, $slugs = []){
        return $query->with(['active_services' => function($query) use($slugs){
            return $query->whereIn('slug', $slugs);
        }]);
    }

    // Узнаем тарифы (Тарифы вынесены твигом для удобного использования в шаблоне)


    public function hasServiceBySlug($slug = ''){
        return !empty($this->active_services->where('slug', $slug)->count());
    }

    public function getHasServiceBaseContactAttribute(){
        return $this->hasServiceBySlug('base_contacts');
    }

    public function getHasServiceContactAttribute(){
        return $this->hasServiceBySlug('extend_contacts');
    }

    public function hasServiceLogo(){
        return $this->hasServiceBySlug('logo_photo');
    }

    public function getHasServiceSendEmailAttribute(){
        return $this->hasServiceBySlug('send_email');
    }

    public function getHasViewFilterAttribute(){
        return $this->hasServiceBySlug('addinfo_search');
    }

    public function getHasServiceScanCopyAttribute(){
        return $this->hasServiceBySlug('addinfo_search');
    }
    public function getHasPromoEventPlacingAttribute(){
        return $this->hasServiceBySlug('promo_event_placing');
    }
    // ============================= Active services Services ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//





    // ============================= Programs ================================= //
    //==========================================================================================//
	/*
		По скольку все необходимые данные лежат в програм-форматах и например цена зависит от типа пользователя, то все что связанно с программами и их форматами
		высчитываются динамически. Для этого созданны геттеры, которые нахродятся, чтобы облегчить жизнь в компонентах и шаблонах
	
	*/
	
    public function getMinPriceAttribute()
    {
        return $this->programs->min('min_price');
    }

    public function getMinPriceViewAttribute()
    {
        $program = $this->programs->sortBy('min_price')->first();
        return !empty($program) ? $program->min_price_view : 'Не указано ';
    }

    public function getMinTermViewAttribute()
    {
        $program = $this->programs->sortBy('min_term_to_sec')->first();
        return !empty($program) ? $program->min_term_view : 'Не указано';
    }


    public function getMaxPriceAttribute()
    {
        return $this->programs->max('max_price');
    }

    public function getMinTermAttribute()
    {
        $program = $this->programs->sortBy('min_term_to_sec')->first();
        return !empty($program) ? $program->min_term_view : null;
    }

    public function getMinTermToSecAttribute(){
        return $this->programs->min('min_term_to_sec');
    }

    public function getMaxTermToSecAttribute(){
        return $this->programs->max('max_term_to_sec');
    }

    public function getMinTermArrayAttribute(){
        $min_term = $this->min_term;
        return !empty($min_term) ? explode(" ", $min_term) : null;
    }
	
	
    public function getCountProgramsAttribute()
    {
        return $this->programs->count();
    }
	
	// для шаблона сахар
    public function getCountProgramsViewAttribute(){
        $str = Lang::choice('программа|программы|программ', $this->count_programs, [], 'ru');
        return "{$this->count_programs} {$str}";
    }

    // Ссылка для добавления программы
    public function getLinkAddProgramAttribute(){
        $id = $this->getOriginalId();
        return "/lk/programs/add/{$id}";
    }
	
	// для лимитов поиска
	
    public static function getLimits($query, $ids){
        return Program::getLimits($query, $ids);
    }


    // метод берет все программы организаций при условии что у программы заполнен хоть 1 формат обучения,
    // соединяет программы с таблицой тарифов и делает все проверки на наличие тарифа, дальше сортирует рандомно и по наличию тарифа
    public function scopeWithProgramsSearch($query, $ids = [], $parameters = []){
        $today = Carbon::today();
        return $query->with(['programs' => function ($query)use($today, $ids, $parameters){
            if(!empty($ids)) $query = $query->whereIn('sector_id', $ids);
            $query->where(function($query) use($parameters){
                $query->notFictive();
                if(!empty($parameters['program_parameters'])) $query = $query->parameters($parameters['program_parameters']);
                return $query;
            })->whereHas('program_formats', function($query) use($parameters){
                return $query->filter($parameters);
            })->with(['program_formats' => function($query) use($parameters) {
                return $query->filter($parameters);
            }]);

            $query->leftJoin('cds_study_object_services as os', function ($join) use($today){
                $join->on('cds_study_programs.id', '=', 'os.object_id')
                    ->where('os.object_type', '=', Program::class)
                    ->where('os.date_start', '<=',$today)->where('os.date_end', '>', $today);})
                ->addSelect(Db::raw('cds_study_programs.*, cast(os.id as bool) as tariff_order'))
                ->orderByRaw('tariff_order asc')->inRandomOrder();
        }]);
    }

	// берем программы с форматами без сортировок 
    public function scopeWithSimplePrograms($query){
        return $query->with(['programs' => function($query){
            return $query->notFictive()->whereHas('program_formats', function($query){
                return $query->notFictive();
            })->with(['program_formats' => function($query){
                return $query->notFictive();
            }]);
        }]);
    }

    // поиск похожих программ
    public function scopeGetSamePrograms($query, $ids = []){
        return $query->whereHas('programs', function ($query) use ($ids) {
            $query->whereIn('sector_id', $ids)->whereHas('program_formats');
        });
    }


    // ============================= Programs ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




    // ============================= Promocode ================================= //
    //==========================================================================================//
	
	// промокод по типу пользователя
    public function getPromocodeAttribute(){
        if($user = Auth::getUser())
        {
            if(!empty($user->type == 2)) return $this->promocode_user;
        }
        return $this->promocode_guest;
    }
	
	// существует ли хоть один промокод
    public function getHasPromocodeAttribute(){
        $promo_user = $this->promocode_user;
        $promo_guest = $this->promocode_guest;
        return !empty($promo_user) && !empty($promo_guest) && (!empty($promo_user->active) || !empty($promo_guest->active));
    }

	// активность промокода  взятого по типу юзверя
    public function getPromocodeActiveAttribute(){
        $promo = $this->getPromocodeAttribute();
        if(!empty($promo) && !empty($promo->active)) return $promo;
        return false;

    }
	
	// обновляем промокоды у организации
    public function updatePromocodes($promocodes = []){
        $codes = Promocode::CODE;
        $model = $this->getOriginalModel();
        foreach($promocodes as $key => $attrs){
            $attributes = ['type' => '', 'organization_id' => $model->id];
            if($key == 'user')
                $promo  = $model->promocode_user();
            else
                $promo = $model->promocode_guest();
            $attributes['type'] = $codes[$key];

            $promo->updateOrCreate(['promocode'=>$attrs['promocode']], $attrs + $attributes);

        }
    }


    // ============================= Promocode ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




    // ============================= LOGO ================================= //
    //==========================================================================================//

    //    тут мы получаем картинку либо существующую либо дефолтную для личного кабинета без просмотра тарифа
    public function getLkLogo(){
        if(!empty($this->logotype)) $logo = $this->logotype->getThumb(250, 250, ['mode' => 'crop']);
        else $logo = $this->getDefaultLogo();
        return $logo;
    }
	
	// дефолтное лого
    public function getDefaultLogo(){
        return '/storage/app/media/org_no_logo.svg';
    }
	
	// для публичного вида лого, показ через тариф
    public function getPublicLogo(){
        if($this->hasServiceLogo()) $logo = $this->getLkLogo();
        else $logo = $this->getDefaultLogo();
        return $logo;


    }

    // ============================= LOGO ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




    // ============================= Actions All ================================= //
    //==========================================================================================//


    // Получение всех оценок (принадлежащие программы и сама организация)
    public function getAllRatesAttribute(){
//        $rates = $this->rates->sum('value');
//        if($rates) $rates /= $this->rates->count();
//        return $rates;

        $rates = $this->comments()->where('rate', '<>', null)->get();
        if ($count = $rates->count()) {
            $sum = $rates->sum('rate');
            $rates = round($sum / $count, 1);
        } else {
            $rates = 0;
        }
        return $rates;
    }

	// все просмотры
    public function getAllViewsAttribute()
    {
        return $this->views->count();
    }
	
	// не мой код
    public function scopeHasActions($query, $actions = null, $onlyMy = true){
        return $query->whereHas('actions', function ($actionsQ) use ($actions, $onlyMy){
            $actionsQ = $actionsQ->action($actions);
            if ($onlyMy)
                $actionsQ = $actionsQ->my();
        });
    }

    //    создаем себе просмотр
    public function createView($attributes = []){
        UserAction::createViews(['object_type' => self::class, 'object_id' => $this->id]);
    }
	
	// не мой код
    public function scopeHasMyFavorite($query){
        return $query->whereHas('my_favorite');
    }


    // ============================= Actions All ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//




    // ============================= Other Getters ================================= //
    //==========================================================================================//
	
	// не мой код
    public function getCountCommentsAttribute()
    {
        return $this->comments()->count();
    }

	// есть ли контактные лица у организации
    public function getHasContactPeopleAttribute(){
        return $this->contact_people()->count();
    }

    // ============================= Other Getters ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//



    // ============================= Other Scopes ================================= //
    //==========================================================================================//


    // поиск организаций по инн
    public function scopeByInn($query, $inn = ''){
        if ($inn == '') return false;
        return $query->where('inn', 'ILIKE', $inn . '%');
    }

    // вдруг пригодится
    public function scopeNotDel($query){
        return $query->whereNull('deleted_at');
    }
	
	// не мой код
    public static function scopeAll(Builder $queryBuilder): Builder{
        return $queryBuilder->where('id', '>', 0);
    }
	
	// не мой код
    public static function scopeById(Builder $queryBuilder, int $id): Builder{
        return $queryBuilder->where('id', '=', $id);
    }

    // все организации юзверя
    public function scopeMy($query){
        return $query->where('user_id', Auth::id())->notDel();
    }
	
	// для фильтра по параметрам
    public function scopeParameters($query, $params = []){
        return $query->whereHas('parameters', function($query) use ($params){
            $query->whereIn('parameter_id', $params);
        }, '>=', count($params));
    }
	
	// поиск по названию организации
    public function scopeSearchByPhrase($query, $phrase = ''){
        if(!empty($phrase)){
            $order = $phrase . '%';
            $phrase = "%{$phrase}%";
        }
        $result = $query->where('name', 'ILIKE', $phrase);
        if(!empty($order)) $result->orderByRaw("name ILIKE '{$order}' desc");
        return $result;
    }
	
	// готовая строка для поиска (иногда удобно)
    public function scopeWherePhrases($query, $phrases = ''){
        if(empty($phrases)) return $query;
        return $query->whereRaw($phrases);
    }
	
	// жадная прогрузка акций со скоупом
    public function scopeWithStocks($query){
        return $query->with(['stocks' => function($query){
            return $query->active()->with('image');
        }]);
    }
	
	// скоуп для подгрузки только модерируемых акций
    public function scopeWithModerateStocks($query){
        return $query->with(['stocks' => function($query){
            return $query->WithOutModerate()->GetWithOutOriginal()->active()->with('image');
        }]);
    }
	// жадная прогрузка мероприятий со  по датам
    public function scopeWithEvents($query){
        return $query->with(['events' => function($query){
            return $query->betweenDate()->with('image');
        }]);
    }
	
	// скоуп для подгрузки только модерируемых мероприятий
    public function scopeWithModerateEvents($query){
        return $query->with(['events' => function($query){
            return $query->WithOutModerate()->getWithOutOriginal()->betweenDate()->with('image');
        }]);
    }
	
	// скоуп для подгрузки только модерируемых контактных лиц
    public function scopeWithModerateContactPeople($query){
        return $query->with(['contact_people' => function($query){
            return $query->WithOutModerate()->getWithOutOriginal()->with('avatar');
        }]);
    }

	// для лк скоуп чтобы никто связные модели не мог редактировать (для безопасности)
    public function scopeAllowed($q){
        return $q->where(function($query){
            $query->whereHas('organization', function($orgQ) {
                return $orgQ->withOutModerate()->my();
            })->orWhereNull('organization_id');
        });
    }
	// скоуп для всего сайта,кроме лк и админки по городам
    public function scopeByCity($query){
        $realm = Session::get('realm');
        if ($realm['isArea']) {
            return $query->whereIn('realm_id', $realm['city']);
        } else {
            return $query->where('realm_id', $realm['id']);
        }
    }
	
	// скоуп для поиска организаций по инн
    public function scopeNotInUser($query){
        $user = Auth::getUser();
        if(!empty($user)) return $query->where('user_id', '<>', $user->id);
        return $query;
    }


    // ============================= Other Scopes ================================= //
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


    protected $dirtyFields = ['is_general', 'name', 'realm_id', 'tariff_id', 'params',
        'description', 'address', 'inn', 'region', 'metro', 'type', 'email', 'site_url'];

    protected $dirtyRelationsAttachOne = ['logotype', 'price'];
    protected $dirtyRelationsHasMany = ['contact_people', 'events', 'stocks', 'certificates' ];
    protected $dirtyRelationsHasOne = [];
    protected $dirtyRelationsAttachMany = ['images'];
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

	// Глобальные скоупы на модерации и отклонено (НЕ ИСПОЛЬЗОВАТЬ БОЛЬШЕ НИКОГДА ЭТО БОЛЬНО)
    public static function boot(){
        parent::boot();
        self::addGlobalScope('notDuplicate', function($query){
            return $query->whereNull('cds_study_organizations.original_id');
        });

        self::addGlobalScope('isActiveStatus', function($query){
            return $query->whereNotIn('cds_study_organizations.status', self::UPDATE_STATUSES);
        });
    }

    // ============================= Duplicate ================================= //
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!//


    // ============================= Move Duplicate ================================= //
    //==========================================================================================//

	// ключ который нужен для проставки аттрибута у связных моделей, у которых нет своих оригиналов
    protected $key = 'organization_id';
	// кидаем в оригинал, что он сливается с дубликатом, для создания уведомления
    public $movingDuplicate = false;
	
	// контроллер сохранения дубликатов
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

	// сохранение аттрибутов
    public function saveDuplicateFields($original = null){
        foreach($this->dirtyFields as $field){
            $original->{$field} = $this->{$field};
        }
    }

	// сохранение связи для дубликата AttachOne
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
	
	// сохранение связи для дубликата HasMany
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
	
	// сохранение связи для дубликата AttachMany
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

	// не мой код
    public function fillRealmFromAddress() {
        $api_key = "dac4eac6-d3da-4641-b95d-b4479c72b432";
        // делаем запрос к API яндекса для разбора адреса
        if ( empty($address = $this->address) )
            return null;

        $arrContextOptions = [
            "ssl" => [
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ],
        ];

        $address = urlencode($address);
        $api_url = "https://geocode-maps.yandex.ru/1.x/?format=json&apikey={$api_key}&geocode={$address}&kind=locality";
        $geocode =  file_get_contents($api_url, false, stream_context_create($arrContextOptions));
        $re = '/\"LocalityName\"\:\s*\"([^\"]*)/mu';
        $mathces = [];
        preg_match_all($re, $geocode, $mathces, PREG_SET_ORDER, 0);
        foreach($mathces as $match) {
            if (empty($city = $match[1]))
                continue;
            if (!empty($realm_id = Realm::cities()->byName($city)->value('id')))
                break;
        }

        if (empty($realm_id))
            return null;
        $this->realm_id = $realm_id;
        return $realm_id;
    }
	
	// смоздаем уведомление для пользователя в зависимости от события
    public function createNotify(){
        if(empty($this->user_id)) return;
        if($this->isOriginal() && $this->wasChanged('status') && $this->status != 1){
            $this->notification()->create(['user_id' => $this->user_id, 'type' => 5, 'text' => $this->status]);
        }
        elseif($this->isDuplicate() && $this->wasChanged('status') && $this->status == 3){
            $original = $this->getOriginalModel();
            $original->notification()->create(['user_id' => $original->user_id, 'type' => 11, 'text' => '3']);
        }
        elseif($this->isOriginal() && $this->movingDuplicate){
            $this->notification()->create(['user_id' => $this->user_id, 'type' => 10, 'text' => '2']);
        }

    }
}
