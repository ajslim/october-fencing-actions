<?php namespace Ajslim\FencingActions\Models;

use Model;

/**
 * Bout Model
 */
class Bout extends Model
{
    protected $connection = 'business';
    
    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_bouts';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['left_fencer_id', 'right_fencer_id', 'tournament_id', 'left_score', 'right_score'];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [
        'bout' => 'Ajslim\Fencingactions\Models\Bout',
        'tournament' => 'Ajslim\Fencingactions\Models\Tournament',
        'left_fencer' => ['Ajslim\Fencingactions\Models\Fencer', 'left_fencer_id'],
        'right_fencer' => ['Ajslim\Fencingactions\Models\Fencer', 'right_fencer_id'],
    ];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    public function getNameAttribute()
    {
        if ($this->cache_name) {
            return $this->cache_name;
        }

        // Update the bouts name and cache it
        $bout = Bout::find($this->id);
        $tournament = Tournament::find($bout->tournament_id);
        $leftFencer = Fencer::find($bout->left_fencer_id);
        $rightFencer = Fencer::find($bout->right_fencer_id);

        $name = $tournament->fullname . ': ' .
            $leftFencer->last_name . " " . $leftFencer->first_name .
            '-' .
            $rightFencer->last_name . " " . $rightFencer->first_name;

        $bout->cache_name = $name;
        $bout->save();

        return $name;
    }
}
