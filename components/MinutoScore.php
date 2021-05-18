<?php namespace Ajslim\Fencingactions\Components;

use Ajslim\FencingActions\Models\Action;
use Ajslim\Fencingactions\Models\Vote;
use Ajslim\Fencingactions\Models\Call;
use Ajslim\Fencingactions\Models\VoteComment;

use Ajslim\FencingActions\Repositories\ActionRepository;
use Backend\Facades\BackendAuth;
use Backend\Models\User;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class MinutoScore extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Get your Minuto Score',
            'description' => 'No description provided yet...'
        ];
    }


    public function onRun()
    {
        $get = Input::get();

        if (isset($get['fieid'])) {
            $id = $get['fieid'];
            echo $id;

            $query = 'select last_name, fencer_id, c
from (
    select fencer_id, count(*) as c
         from (
             select fencer_id, opponent_id
                  from (
                      (
                      SELECT id, left_fencer_id as fencer_id, right_fencer_id as opponent_id
                               FROM october_business.ajslim_fencingactions_bouts
                           )
                           union
                           (
                               SELECT id, right_fencer_id as fencer_id, left_fencer_id as opponent_id
                               FROM october_business.ajslim_fencingactions_bouts
                           )
                       ) as X
                  group by fencer_id, opponent_id
              ) as Y
         group by fencer_id
         order by c desc
     ) as Z
         join october_business.ajslim_fencingactions_fencers on fencer_id = ajslim_fencingactions_fencers.id';

        }
    }

}
