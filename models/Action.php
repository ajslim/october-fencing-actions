<?php namespace Ajslim\FencingActions\Models;

use Model;

/**
 * Action Model
 */
class Action extends Model
{
    protected $connection = 'business';
    
    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_actions';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['gfycat_id', 'priority', 'tags'];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [
        'call_on_site' =>
            [
                'Ajslim\Fencingactions\Models\Call',
                'key' => 'call_on_site_id'
            ],
        'tournament' => 'Ajslim\Fencingactions\Models\Tournament',
        'left_fencer' => ['Ajslim\Fencingactions\Models\Fencer', 'left_fencer_id'],
        'right_fencer' => ['Ajslim\Fencingactions\Models\Fencer', 'right_fencer_id'],
    ];
    public $belongsToMany = [
        'tags' => [
            'Ajslim\Fencingactions\Models\Tag',
            'table' => 'ajslim_fencingactions_action_tag',
            'key' => 'action_id',
            'otherKey' => 'tag_id',
        ],
        'alternate_calls' => [
            'Ajslim\Fencingactions\Models\Call',
            'table' => 'ajslim_fencingactions_action_call',
            'key' => 'action_id',
            'otherKey' => 'call_id',
        ]
    ];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public function getGfycatAttribute()
    {
        return $this->gfycat_id;
    }

    public function getTournamentOptions() {

        if (isset($this->bout)) {
            return [];
        }
        return Tournament::all()->lists('name', 'id');

    }
}
