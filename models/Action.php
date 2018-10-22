<?php namespace Ajslim\FencingActions\Models;

use Assetic\Filter\PackerFilter;
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
        'bout' => 'Ajslim\Fencingactions\Models\Bout',
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

    public function getBoutOptions() {
        if (isset($this->tournament)) {
            $tournament = Tournament::find($this->tournament->id);

            // Add an empty option to the beginning of the list
            return [null => 'Unknown / Other'] + Bout::where('tournament_id', '=', $tournament->id)->lists('cache_name', 'id');
        }

        if (isset($this->bout)) {
            return [null => 'Unknown / Other'] + Bout::where('tournament_id', '=', $this->bout->tournament_id)->lists('cache_name', 'id');
        }

        return [null=> "Select Tournament"] + [null => 'Unknown / Other'];
    }

    public function getTournamentOptions() {
        // Add an empty option to the beginning of the list
        return [null => 'Unknown / Other'] + Tournament::all()->lists('fullname', 'id');
    }

    public function getLeftFencerOptions() {
        if (isset($this->bout)) {
            $leftFencer = Fencer::find($this->bout->left_fencer_id);

            // Populate from bout
            return [
                null => "-- " . $leftFencer->name . " --"
            ];
        }

        // Add an empty option to beginning of the list
        return [null => 'Unknown / Other'] + Fencer::all()->lists('name', 'id');;
    }

    public function getRightFencerOptions() {

        if (isset($this->bout)) {
            $rightFencer = Fencer::find($this->bout->right_fencer_id);

            // Populate from bout
            return [
                null => "-- " . $rightFencer->name . " --"
            ];
        }

        // Add an empty option to beginning of the list
        return [null => 'Unknown / Other'] + Fencer::all()->lists('name', 'id');
    }

    public function getLeftnameAttribute()
    {
        if (isset($this->bout)) {
            $leftFencer = Fencer::find($this->bout->left_fencer_id);
        } else {
            $leftFencer = Fencer::find($this->left_fencer_id);
        }

        if ($leftFencer) {
            return $leftFencer->name;
        }

        return '';
    }

    public function getRightnameAttribute()
    {
        if (isset($this->bout)) {
            $rightFencer = Fencer::find($this->bout->right_fencer_id);
        } else {
            $rightFencer = Fencer::find($this->right_fencer_id);
        }

        if ($rightFencer) {
            return $rightFencer->name;
        }

        return '';
    }
}
