<?php namespace Cds\Study\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * User Actions Back-end Controller
 */
class UserActions extends Controller
{
    public $implement = [
        'Backend.Behaviors.FormController',
        'Backend.Behaviors.ListController'
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public function __construct()
    {
        parent::__construct();

        BackendMenu::setContext('Cds.Study', 'study', 'useractions');
    }
}
