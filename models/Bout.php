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
        $bout = Bout::find($this->id);

        return $bout->id . ":" . $bout->tournament->year . " - " . $bout->tournament->place  . ': ' .
            $bout->left_fencer->last_name . " " . $bout->left_fencer->first_name .
            '-' .
            $bout->right_fencer->last_name . " " . $bout->right_fencer->first_name;
    }
}
