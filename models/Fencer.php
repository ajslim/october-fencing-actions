<?php namespace Ajslim\FencingActions\Models;

use Model;

/**
 * Tag Model
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
    protected $fillable = ['first_name', 'last_name', 'fie_site_number', 'fie_number', 'birth', 'country_code'];

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

    public function getFiesitelinkAttribute() {
        return "http://fie.org/fencers/fencer/$this->fie_site_number";
    }

    public function getNameAttribute()
    {
        return $this->last_name . ", " . $this->first_name;
    }
}
