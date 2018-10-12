<?php namespace Ajslim\FencingActions\Models;

use Model;

/**
 * Tag Model
 */
class Tag extends Model
{
    protected $connection = 'business';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_tags';

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
        'actions' => [
            'Ajslim\Fencingactions\Models\Action',
            'table' => 'ajslim_fencingactions_action_tag',
            'key' => 'tag_id',
            'otherKey' => 'action_id',
        ]
    ];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
