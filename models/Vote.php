<?php namespace Ajslim\Fencingactions\Models;

use Model;

/**
 * vote Model
 *
 * @property integer call_id
 * @property integer priority
 *
 * @mixin \Eloquent
 */
class Vote extends Model
{
    protected $connection = 'business';
    protected $touches = ['action'];

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
    protected $fillable = ['action_id', 'user_id'];

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


    public function isSameCall(Vote $vote)
    {
        return (
            $this->priority === $vote->priority
            && $this->call_id === $vote->call_id
            && $this->card_for === $vote->card_for
        );
    }

    public function toString()
    {
        if ($this->vote_comment_id === 2) {
            return 'Not an action';
        }

        if ($this->card_for === Action::LEFT_FENCER_ID) {
            return 'Card for the left fencer';
        }

        if ($this->card_for === Action::RIGHT_FENCER_ID) {
            return 'Card for the right fencer';
        }

        if ($this->priority === Action::LEFT_FENCER_ID) {
            if ($this->call_id !== null) {
                $call = Call::find($this->call_id);
                return $call->name . ' from the the Left';
            }
        }

        if ($this->priority === Action::RIGHT_FENCER_ID) {
            if ($this->call_id !== null) {
                $call = Call::find($this->call_id);
                return $call->name . ' from the the Right';
            }
        }

        if ($this->priority === Action::NEITHER_FENCER_ID) {
            if ($this->call_id !== null) {
                $call = Call::find($this->call_id);
                return $call->name;
            }
        }

        return '';
    }
}
