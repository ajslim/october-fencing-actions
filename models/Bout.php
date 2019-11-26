<?php namespace Ajslim\FencingActions\Models;

use DateTime;
use Model;
use Illuminate\Support\Facades\Cache;

/**
 * Bout Model
 *
 * @mixin \Eloquent
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property Action[] actions
 * @property string   video_url
 * @property string name
 * @property string cache_name
 *
 * @property Fencer left_fencer
 * @property Fencer right_fencer
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
    protected $fillable = ['left_fencer_id', 'right_fencer_id', 'tournament_id', 'left_score', 'right_score', 'cache_name'];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'actions' => 'Ajslim\Fencingactions\Models\Action',
    ];
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


    public $cacheMinutes = 30;

    /**
     * Generate a unique cache key to cache actions
     *
     * @return string
     */
    public function cacheKey()
    {
        return sprintf(
            "%s/%s-%s",
            $this->getTable(),
            $this->getKey(),
            $this->updated_at->timestamp
        );
    }


    /**
     * Returns the number of actions
     *
     * @return integer
     */
    public function getActionCountAttribute()
    {
        return Cache::remember($this->cacheKey() . ':actionCount', $this->cacheMinutes, function () {
            $actionCount = count($this->actions);
            return $actionCount;
        });
    }


    /**
     * Returns the number of actions
     *
     * @return Fencer
     */
    public function getWinnerAttribute()
    {
        if ($this->left_score > $this->right_score) {
            return $this->left_fencer;
        }
        return $this->right_fencer;
    }


    /**
     * Returns the number of actions
     *
     * @return Fencer
     */
    public function getLoserAttribute()
    {
        if ($this->left_score < $this->right_score) {
            return $this->left_fencer;
        }
        return $this->right_fencer;
    }



    /**
     * Reverses left and right fencer
     *
     * @return void
     */
    public function reverseFencers()
    {
        $bout = Bout::find($this->id);

        $temp = $bout->left_fencer_id;
        $bout->left_fencer_id = $bout->right_fencer_id;
        $bout->right_fencer_id = $temp;

        $temp = $bout->left_score;
        $bout->left_score = $bout->right_score;
        $bout->right_score = $temp;

        $bout->cache_name = Bout::generateName($bout);
        $bout->fencers_reversed = new DateTime();

        $bout->save();
    }


    /**
     * Counts the number of reversed fencer votes made after the fencers were last reversed
     *
     * @return int|null
     */
    public function getFencersWrongWayWarningAttribute()
    {
        $count = 0;
        foreach ($this->actions as $action) {
            foreach ($action->votes as $vote) {
                if ($vote->vote_comment_id === 3
                    && (
                        $this->fencers_reversed === null
                        || $vote->created_at > $this->fencers_reversed
                        )
                ) {
                        $count += 1;
                }
            }
        }

        if ($count > 0) {
            return $count;
        }

        return null;
    }


    /**
     * Generates a name for the bout based on the tournament
     * and fencers names
     *
     * @param Bout $bout Bout to generate name from
     *
     * @return string
     */
    public static function generateName($bout): string
    {
        $tournament = Tournament::find($bout->tournament_id);
        $leftFencer = Fencer::find($bout->left_fencer_id);
        $rightFencer = Fencer::find($bout->right_fencer_id);

        return $tournament->fullname . ': '
            . $leftFencer->last_name . " " . $leftFencer->first_name
            . '-' . $rightFencer->last_name . " " . $rightFencer->first_name;
    }


    /**
     * Updates the cached name on the bout
     *
     * @return string
     */
    public function updateCacheName(): string
    {
        $bout = Bout::find($this->id);

        // Update the bouts name and cache it
        $bout->cache_name = $this->generateName($bout);
        $bout->save();

        return $bout->cache_name;
    }


    /**
     * Returns the cached name of the bout or creates a name
     * in the form
     *
     * <Tournament Full name>: <left name>-<right name>
     *
     * and caches it
     *
     * @return string
     */
    public function getNameAttribute()
    {
        if ($this->cache_name !== null) {
            return $this->cache_name;
        }

        $name = $this->updateCacheName();

        return $name;
    }
}
