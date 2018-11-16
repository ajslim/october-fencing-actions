<?php namespace Ajslim\Fencingactions\Components;

use Ajslim\FencingActions\Models\Action;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\Fencingactions\Models\Call;
use Ajslim\Fencingactions\Models\VoteComment;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;


class Browse extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Browse the API component',
            'description' => 'No description provided yet...'
        ];
    }


    public function onRun()
    {
    }


    public function defineProperties()
    {
        return [];
    }
}
