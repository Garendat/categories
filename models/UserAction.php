<?php namespace Cds\Study\Models;

use Cds\Study\Classes\Models\Action as CdsAction;
use Auth;
use Illuminate\Support\Facades\Mail;
use Session;
use Cds\Study\Models\Program;
use Cds\Study\Models\Sector;
use Db;
use Cds\Study\Models\User;

/**
 * favorite Model
 */
class UserAction extends CdsAction
{
    const STATISTICS = [
        'site_url' => 'Клики на сайт',
        'promocode' => 'Клики на промокод',
        'phone' => 'Клики на телефон',
        'request' => 'Отправленные заявки'
    ];

    const OBJECT_NAMES = [
        Organization::class => 'org',
        Program::class => 'prg',
    ];

    const CLASSES = [
        'org' => Organization::class,
        'prg' => Program::class
    ];

    public $attributes = [
        'value' => 0,
    ];
    public $belongsTo = [
        'user' => User::class
    ];

    public $morphOne = [
        'notification' => [
            Notification::class,
            'name' => 'object',
        ],
    ];

    // ============================= Before After ================================= //
    function beforeValidate() {
        if ( empty($this->user_id) and empty($this->session_token) )
            $this->user_id = Auth::check() ? Auth::id() : Session::get('_token');
    }

    public function afterCreate()
    {

        //когда оценили комментарий, то уведомляем автора комментария
        if (!empty($this->object->user_id) && $this->action == 'rate' && $this->object_type == Comment::class) {
            $data = [
                'user_id' => $this->object->user_id,
                'type' => 2
            ];

            $this->notification()->create($data);
        }

        //когда оценили программу обучения, то уведомляем автора организации
        if (!empty($this->object->organization->user_id) && $this->action == 'rate' && $this->object_type == Program::class) {
            $data = [
                'user_id' => $this->object->organization->user_id,
                'type' => 3
            ];

            $this->notification()->create($data);
        }
    }

    // ============================= Getters Setters ============================== //

    function getObjAttribute() {
        return [
            'name' => self::OBJECT_NAMES[$this->object_type],
            'id' => $this->object_id,
            'type' => $this->object_type,
        ];
    }


    // ============================= Scopes filter ================================ //

    public function scopeMy($q)
    {
        if ( Auth::check() )
            return $q->where('user_id', Auth::id());
        return $q->where('session_token', Session::get('_token'));
    }

    public function scopeByObject($q, $object = null)
    {
        if ($object instanceof self) {
            $id = $object->object_id;
            $type = $object->object_type;
        } elseif ($object instanceof \Model) {
            $id = $object->id;
            $type = get_class($object);
        } else {
            $id   = array_get($object, 'id', 0);
            $name = array_get($object, 'name', 'org');
            $type = array_get($object, 'type', array_get(self::CLASSES, $name));
        }
        return $q
            ->where('object_id', $id)
            ->where('object_type', $type);
    }

    // ============================= Make Scopes ================================== //

    //удаляет существующую запись действия пользователя
    function scopeMakeToggle($q, $newData = []) {
        $object_name = array_get($newData, 'object_name');

        if ( empty(array_get($newData, 'object_type')) )
            $newData['object_type'] = self::CLASSES[$object_name];
        $act = $q->first();
        if (empty($act)) {
            $act = self::create($newData + ['value' => 1]);
        } else {
            $act->delete();
            $act = (new self)->fill($newData + ['value' => 0]);
        }

        return $act;
    }

    // ============================= Protected Methods ============================ //
    // ============================= Public Methods =============================== //

    public static function UpdateOrCreateRequest($search_arr = [], $update_arr){
        $search_arr['user_id'] = Auth::getUser()->id;
        $search_arr['action'] = 'request';
        $search_arr['object_type'] = Organization::class;
        $model = self::updateOrCreate($search_arr, ['value' => 1] + $update_arr);
        return $model;
    }


    public function getAnonymAvatar($size)
    {
        return '//www.gravatar.com/avatar/?s=' . $size. '&d=mm';
    }

    public static function createViews($attributes = []){
        if (Auth::check()) $attributes['user_id'] = Auth::id();
        else   $attributes['session_token'] = Session::get('_token');
        self::whereRaw('extract(minute from (now() - updated_at::timestamp)) < 15')->updateOrCreate($attributes, ['action' => 'view']);
    }
    public static function createViewsPromocode($attributes = []){
        if (Auth::check()) $attributes['user_id'] = Auth::id();
        else   $attributes['session_token'] = Session::get('_token');
        self::whereRaw('extract(minute from (now() - updated_at::timestamp)) < 15')->updateOrCreate($attributes, ['action' => 'promocode']);
    }

    public static function createActionOnOrganization($organization_id = null, $action = null){
        $attributes = [
            'object_type' => Organization::class,
            'object_id'   => $organization_id,
            'action'      => $action
        ];
        if (Auth::check()) $attributes['user_id'] = Auth::id();
        else   $attributes['session_token'] = Session::get('_token');
        self::whereRaw('extract(minute from (now() - updated_at::timestamp)) < 15')->updateOrCreate($attributes, []);
    }

    public function scopeByPrograms($query, $ids = []){
        return $query->where('object_type', Program::class)->whereIn('object_id', $ids);
    }
    public function scopeByOrganizations($query, $ids = []){
        return $query->where('object_type', Organization::class)->whereIn('object_id', $ids);
    }

    public function scopeGetRequest($query){
        return $query->where('action', 'request');
    }

    public function scopeByValue($query, $value = 1){
        return $query->where('value', 1);
    }



//    public function scopeAction($query, $action){
//        return $query->where('action', $action);
//    }

    public static function sendOrganizationEmail(){
        $organizations = Organization::whereNotNull('email')->whereHas('actions')->limit(1)->pluck('email', 'id');
        foreach($organizations as $org_id => $org_email){
            // email => $org_email
            $data = self::getStatisticsByOrganizations([$org_id]);
            Mail::sendTo('ko4etov.volodya@yandex.ru', 'cds.study::mail.statistics', ['data' => $data]);
        }

    }

    public static function sendOrganizationEmailByUser(){
        $users = User::whereNotNull('email')->whereHas('organizations', function($query){
            return $query->whereHas('actions');
        })->with(['organizations' => function($query){
            return $query->whereHas('actions');
        }])->get();

        foreach($users as $user){
            $data = self::getStatisticsByOrganizations($user->organizations->pluck('id'));
            // email => $user->email
                Mail::sendTo('ko4etov.volodya@yandex.ru', 'cds.study::mail.statistics', ['data' => $data]);
        }
    }



    public static function getStatisticsByOrganizations($ids = [], $start = '', $end = '') {
        $sectors = Sector::join('cds_study_programs as pr', function($join) use($ids){
            $join->on('cds_study_sectors.id', '=', 'pr.sector_id')
                ->whereIn('pr.organization_id', $ids);
        })->join('cds_actions as ac', function($join) use($start, $end){
            $join->on('pr.id', '=', 'ac.object_id')
                ->where('ac.object_type', '=', Program::class)
                ->where('ac.action', '=', 'view');
            if(!empty($start)) $join->whereDate('ac.updated_at', '>=', $start);
            if(!empty($end)) $join->whereDate('ac.updated_at', '<=', $end);
        })->groupBy('cds_study_sectors.id')->addSelect(Db::raw('cds_study_sectors.*, count(ac.id) as count'))->pluck('count', 'name');
        $actions = self::byOrganizations($ids)->groupBy('action');
        if(!empty($start)) $actions = $actions->where('updated_at', '>=', $start);
        if(!empty($end)) $actions = $actions->where('updated_at', '<=', $end);
        $actions = $actions->addSelect(Db::raw('action, count(*) as count'))->pluck('count', 'action');
        $result = [  ['Посещения', $actions->get('view', 0), 'Общее']  ];
        foreach($sectors as $name => $count){
            $result[] = [null, $count, $name];
        }
        foreach(self::STATISTICS as $key => $event){
            $result[] = [$event, $actions->get($key, 0)];
        }
        return $result;
    }

}
