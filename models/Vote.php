<?php namespace Ajslim\Fencingactions\Models;

use Model;

/**
 * vote Model
 */
class Vote extends Model
{
    protected $connection = 'business';

    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_votes';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['action_id'];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [];
    public $belongsTo = [
        'call' => 'Ajslim\Fencingactions\Models\Call',
        'vote_comment' => 'Ajslim\Fencingactions\Models\VoteComment',
        'action' => 'Ajslim\Fencingactions\Models\Action',
    ];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
