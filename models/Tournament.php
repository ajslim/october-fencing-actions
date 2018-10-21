<?php namespace Ajslim\Fencingactions\Models;

use Model;

/**
 * tournament Model
 */
class Tournament extends Model
{
    protected $connection = 'business';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_tournaments';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'year',
        'fie_id',
        'name',
        'place',
        'weapon',
        'country_code',
        'start_date',
        'end_date',
        'category',
        'gender',
        'type',
        'event',
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'bouts' => 'Ajslim\Fencingactions\Models\Bout',
        'actions' => 'Ajslim\Fencingactions\Models\Action',
    ];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
