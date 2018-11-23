<?php namespace Ajslim\FencingActions\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
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


    public function getAllFencersAverageActionsFor()
    {
        $returnCollection = new Collection();
        $allFencers = Fencer::all();
        foreach ($allFencers as $fencer) {
            $returnCollection = $returnCollection->merge($fencer->getActionsForAttribute());
        }
        return $returnCollection;
    }

    /**
     * Returns the calls array using a cache
     *
     * @return Collection
     */
    public function getAllFencersAverageActionsCallPercentagesAttribute()
    {
        // This doesn't use the cache key, and will re run every 30 minutes
        return Cache::remember('allFencersAverageCallPercentages', 30, function () {
            return $this->getAllFencersAverageActionsCallPercentages();
        });
    }

    public function getAllFencersAverageActionsCallPercentages()
    {
        $actions = $this->getAllFencersAverageActionsFor();

        $totalNumberOfActions = count($actions);

        if ($totalNumberOfActions === 0) {
            return [];
        }

        $calls = [
            Call::ATTACK_ID => 0,
            Call::COUNTER_ATTACK_ID => 0,
            Call::RIPOSTE_ID => 0,
            Call::REMISE_ID => 0,
            Call::LINE_ID => 0,
            Call::OTHER_ID => 0,
            Call::SIMULTANEOUS_ID => 0,
        ];

        foreach ($actions as $action) {
            $topVote = $action->getTopVoteAttribute();
            if (isset($calls[$topVote->callId])) {
                $calls[$topVote->callId] += 1;
            }
        }

        foreach ($calls as $callId => $call) {
            $calls[$callId] = number_format(($call / $totalNumberOfActions), 3);
        }

        return $calls;
    }



    /**
     * Returns the calls array using a cache
     *
     * @return Collection
     */
    public function getActionsForAttribute()
    {
        return Cache::remember($this->cacheKey() . ':actions', $this->cacheMinutes, function () {
            return $this->getActionsFor();
        });
    }


    /**
     * Gets all the fencers actions
     *
     * @return Action[]|Collection
     */
    public function getActionsFor()
    {
        $returnCollection = new Collection();

        $leftActions = $this
            ->hasManyThrough(
                'Ajslim\Fencingactions\Models\Action',
                'Ajslim\Fencingactions\Models\Bout',
                'left_fencer_id',
                'bout_id',
                'id',
                'id'
            )
            ->get();


        /* @var Action $action */
        foreach ($leftActions as $action) {
            $topVote = $action->getTopVoteAttribute();

            if ($topVote !== false && $topVote->priorityId === Action::LEFT_FENCER_ID) {
                $returnCollection->push($action);
            }

            if ($topVote !== false && $topVote->callId === Call::SIMULTANEOUS_ID) {
                $returnCollection->push($action);
            }
        }

        $rightActions = $this
            ->hasManyThrough(
                'Ajslim\Fencingactions\Models\Action',
                'Ajslim\Fencingactions\Models\Bout',
                'right_fencer_id',
                'bout_id',
                'id',
                'id'
            )
            ->get();

        /* @var Action $action */
        foreach ($rightActions as $action) {
            $topVote = $action->getTopVoteAttribute();

            if ($topVote !== false && $topVote->priorityId === Action::RIGHT_FENCER_ID) {
                $returnCollection->push($action);
            }

            if ($topVote !== false && $topVote->callId === Call::SIMULTANEOUS_ID) {
                $returnCollection->push($action);
            }
        }

        return $returnCollection;
    }



    /**
     * Returns the calls array using a cache
     *
     * @return Collection
     */
    public function getActionsAgainstAttribute()
    {
        return Cache::remember($this->cacheKey() . ':actionsAgainst', $this->cacheMinutes, function () {
            return $this->getActionsAgainst();
        });
    }


    /**
     * Gets all the fencers actions
     *
     * @return Action[]|Collection
     */
    public function getActionsAgainst()
    {
        $returnCollection = new Collection();

        $leftActions = $this
            ->hasManyThrough(
                'Ajslim\Fencingactions\Models\Action',
                'Ajslim\Fencingactions\Models\Bout',
                'left_fencer_id',
                'bout_id',
                'id',
                'id'
            )
            ->get();


        /* @var Action $action */
        foreach ($leftActions as $action) {
            $topVote = $action->getTopVoteAttribute();

            if ($topVote !== false && $topVote->priorityId === Action::RIGHT_FENCER_ID) {
                $returnCollection->push($action);
            }

            if ($topVote !== false && $topVote->callId === Call::SIMULTANEOUS_ID) {
                $returnCollection->push($action);
            }
        }

        $rightActions = $this
            ->hasManyThrough(
                'Ajslim\Fencingactions\Models\Action',
                'Ajslim\Fencingactions\Models\Bout',
                'right_fencer_id',
                'bout_id',
                'id',
                'id'
            )
            ->get();

        /* @var Action $action */
        foreach ($rightActions as $action) {
            $topVote = $action->getTopVoteAttribute();

            if ($topVote !== false && $topVote->priorityId === Action::LEFT_FENCER_ID) {
                $returnCollection->push($action);
            }

            if ($topVote !== false && $topVote->callId === Call::SIMULTANEOUS_ID) {
                $returnCollection->push($action);
            }
        }

        return $returnCollection;
    }


    /**
     * Returns the calls array using a cache
     *
     * @return Collection
     */
    public function getCallPercentagesAttribute()
    {
        return Cache::remember($this->cacheKey() . ':callPercentages', $this->cacheMinutes, function () {
            return $this->getCallPercentages();
        });
    }


    public function getCallPercentages()
    {
        $actions = $this->getActionsForAttribute();

        $totalNumberOfActions = count($actions);

        if ($totalNumberOfActions === 0) {
            return [];
        }

        $calls = [
            Call::ATTACK_ID => 0,
            Call::COUNTER_ATTACK_ID => 0,
            Call::RIPOSTE_ID => 0,
            Call::REMISE_ID => 0,
            Call::LINE_ID => 0,
            Call::OTHER_ID => 0,
            Call::SIMULTANEOUS_ID => 0,
        ];

        foreach ($actions as $action) {
            $topVote = $action->getTopVoteAttribute();
            if (isset($calls[$topVote->callId])) {
                $calls[$topVote->callId] += 1;
            }
        }

        foreach ($calls as $callId => $call) {
            $calls[$callId] = number_format(($call / $totalNumberOfActions), 3);
        }

        return $calls;
    }


    /**
     * Returns the calls array using a cache
     *
     * @return Collection
     */
    public function getCallPercentagesAgainstAttribute()
    {
        return Cache::remember($this->cacheKey() . ':callPercentagesAgainst', $this->cacheMinutes, function () {
            return $this->getCallPercentagesAgainst();
        });
    }

    public function getCallPercentagesAgainst()
    {
        $actions = $this->getActionsAgainstAttribute();

        $totalNumberOfActions = count($actions);

        if ($totalNumberOfActions === 0) {
            return [];
        }

        $calls = [
            Call::ATTACK_ID => 0,
            Call::COUNTER_ATTACK_ID => 0,
            Call::RIPOSTE_ID => 0,
            Call::REMISE_ID => 0,
            Call::LINE_ID => 0,
            Call::OTHER_ID => 0,
            Call::SIMULTANEOUS_ID => 0,
        ];

        foreach ($actions as $action) {
            $topVote = $action->getTopVoteAttribute();
            if (isset($calls[$topVote->callId])) {
                $calls[$topVote->callId] += 1;
            }
        }

        foreach ($calls as $callId => $call) {
            $calls[$callId] = number_format(($call / $totalNumberOfActions), 3);
        }

        return $calls;
    }



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
