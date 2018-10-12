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
    protected $fillable = [];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
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
