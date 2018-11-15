<?php namespace Ajslim\FencingActions\Models;

use Model;

/**
 * Call Model
 */
class Call extends Model
{
    protected $connection = 'business';

    public const ATTACK_ID = 1;
    public const COUNTER_ATTACK_ID = 2;
    public const RIPOSTE_ID = 3;
    public const REMISE_ID = 4;
    public const LINE_ID = 5;
    public const OTHER_ID = 6;
    public const SIMULTANEOUS_ID = 7;

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_calls';

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
    public $hasMany = [];
    public $belongsTo = [];
    public $belongsToMany = [
        'actions' => 'Ajslim\Fencingactions\Models\Action',
    ];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
