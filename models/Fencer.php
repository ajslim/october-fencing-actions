<?php namespace Ajslim\FencingActions\Models;

use Model;
use October\Rain\Database\QueryBuilder;

/**
 * Tag Model
 *
 * @mixin \Eloquent
 *
 * @method QueryBuilder left_bouts
 * @method QueryBuilder right_bouts
 *
 * @property string last_name
 * @property string first_name
 */
class Fencer extends Model
{
    protected $connection = 'business';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_fencers';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * Gets populated by script
     *
     * @var array Fillable fields
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'fie_site_number',
        'fie_number',
        'birth',
        'country_code',
        'gender'
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'left_bouts' => [
            'Ajslim\Fencingactions\Models\Bout',
            'key' => 'left_fencer_id'
        ],
        'right_bouts' => [
            'Ajslim\Fencingactions\Models\Bout',
            'key' => 'right_fencer_id'
        ],
        // Add the relation to the array. The fencer->bouts() method must be defined below.
        'bouts' => []
    ];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];


    /**
     * Gets the FIE site link
     *
     * @return string
     */
    public function getFiesitelinkAttribute()
    {
        return "http://fie.org/fencers/fencer/$this->fie_site_number";
    }


    /**
     * Gets last name, first name of fencer
     *
     * @return string
     */
    public function getNameAttribute()
    {
        return $this->last_name . ", " . $this->first_name;
    }


    /**
     * Gets the combined left_bouts and right_bouts
     *
     * @return QueryBuilder The combined bouts
     */
    public function bouts()
    {
        $leftBouts = $this->left_bouts();
        $rightBouts = $this->right_bouts();

        $merged = $leftBouts->unionAll($rightBouts);
        return $merged;
    }
}
