<?php namespace Ajslim\Fencingactions\Models;

use Model;

/**
 * tournament Model
 *
 * @mixin \Eloquent
 *
 * @property string year
 * @property string place
 * @property string weapon
 * @property string name
 * @property string gender
 */
class TournamentResult extends Model
{
    protected $connection = 'business';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_tournament_results';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['id'];


    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
