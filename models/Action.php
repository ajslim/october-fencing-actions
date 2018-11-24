<?php namespace Ajslim\FencingActions\Models;

use Ajslim\FencingActions\Utility\Utility;
use Assetic\Filter\PackerFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Model;

/**
 * Action Model
 *
 * @mixin \Eloquent
 *
 * @propery integer left_fencer_id
 * @propery integer right_fencer_id
 * @propery Bout bout
 * @propery Collection votes
 * @method Builder votes
 */
class Action extends Model
{
    protected $connection = 'business';

    public const NEITHER_FENCER_ID = 0;
    public const LEFT_FENCER_ID = 1;
    public const RIGHT_FENCER_ID = 2;

    private $verifiedConfidenceThreshold = .80;
    private $numberOfVerifiers = 2;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'ajslim_fencingactions_actions';

    /**
     * @var array Guarded fields
     */
    protected $guarded = ['*'];

    /**
     * @var array Fillable fields
     */
    protected $fillable = ['bout_id', 'video_url', 'thumb_url', 'priority', 'tags'];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'votes' => 'Ajslim\Fencingactions\Models\Vote'
    ];
    public $belongsTo = [
        'call_on_site' =>
            [
                'Ajslim\Fencingactions\Models\Call',
                'key' => 'call_on_site_id'
            ],
        'bout' => 'Ajslim\Fencingactions\Models\Bout',
        'tournament' => 'Ajslim\Fencingactions\Models\Tournament',
        'left_fencer' => ['Ajslim\Fencingactions\Models\Fencer', 'left_fencer_id'],
        'right_fencer' => ['Ajslim\Fencingactions\Models\Fencer', 'right_fencer_id'],
    ];
    public $belongsToMany = [
        'tags' => [
            'Ajslim\Fencingactions\Models\Tag',
            'table' => 'ajslim_fencingactions_action_tag',
            'key' => 'action_id',
            'otherKey' => 'tag_id',
        ]
    ];
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
     * Returns the verified vote, or false
     *
     * @return boolean | Vote
     */
    public function getVerifiedVoteAttribute()
    {
        return Cache::remember($this->cacheKey() . ':verifiedCall', $this->cacheMinutes, function () {
            return $this->getVerifiedCall();
        });
    }


    /**
     * A call can be verified by an FIE ref, or by having a number of verifiers agree with
     * the majority with a certain confidence value
     *
     * @return bool|Vote
     */
    public function getVerifiedCall()
    {
        $fieConsensus = $this->getFieConsensusVoteAttribute();
        if ($fieConsensus !== false) {
            return $fieConsensus;
        }

        $verifierVotes = $this->getVerifierVotes();
        if ($this->getConfidenceAttribute() > $this->verifiedConfidenceThreshold
            && $verifierVotes->count() >= $this->numberOfVerifiers) {
            return $verifierVotes->first();
        }

        return false;
    }


    public function getVerifierVotes()
    {
        return $this->votes()
            ->where('referee_level', 'verifier')
            ->get();
    }


    public function getFieConsensusVoteAttribute()
    {
        return Cache::remember($this->cacheKey() . ':fieConsensus', $this->cacheMinutes, function () {
            return $this->getFieConsensusVote();
        });
    }


    public function getFieConsensusVote()
    {
        $fieVotes = $this
            ->votes()
            ->where('referee_level', 'fie')
            ->where('vote_comment_id', '!=', 2)
            ->get();

        if (count($fieVotes) === 0) {
            return false;
        }

        $lastVote = null;
        foreach ($fieVotes as $fieVote) {
            if ($lastVote !== null
                && ($fieVote->priority !== $lastVote->priority
                    || $fieVote->card_for !== $lastVote->card_for
                    || $fieVote->call_id !== $lastVote->call_id)) {
                    return false;
            }
            $lastVote = $fieVote;
        }

        return $lastVote;
    }


    public function getFieDifficultyFloorAttribute()
    {
        return Cache::remember($this->cacheKey() . ':getFieDifficultyFloor', $this->cacheMinutes, function () {
            return $this->getFieDifficultyFloor();
        });
    }


    public function getFieDifficultyFloor()
    {
        $fieVotes = $this
            ->votes()
            ->where('referee_level', 'fie')
            ->where('vote_comment_id', '!=', 2)
            ->get();

        if (count($fieVotes) === 0) {
            return false;
        }

        $lowest = false;
        foreach ($fieVotes as $fieVote) {
            if ($lowest === false || $fieVote->difficulty < $lowest) {
                $lowest = $fieVote;
            }
        }

        return $lowest;
    }


    /**
     * Returns the calls array using a cache
     *
     * @return Collection
     */
    public function getCachedCallsArray()
    {
        return Cache::remember($this->cacheKey() . ':callsArray', $this->cacheMinutes, function () {
            return $this->getCallsArray();
        });
    }


    /**
     * Returns an array of all the votes indexed by priority, then call id
     *
     * @return array
     */
    private function getCallsArray()
    {
        $calls = [
            Action::LEFT_FENCER_ID => [
                Call::ATTACK_ID => 0,
                Call::COUNTER_ATTACK_ID => 0,
                Call::RIPOSTE_ID => 0,
                Call::REMISE_ID => 0,
                Call::LINE_ID => 0,
                Call::OTHER_ID => 0,
                Call::SIMULTANEOUS_ID => 0,
                Call::CARD_ID => 0
            ],
            Action::RIGHT_FENCER_ID => [
                Call::ATTACK_ID => 0,
                Call::COUNTER_ATTACK_ID => 0,
                Call::RIPOSTE_ID => 0,
                Call::REMISE_ID => 0,
                Call::LINE_ID => 0,
                Call::OTHER_ID => 0,
                Call::SIMULTANEOUS_ID => 0,
                Call::CARD_ID => 0
            ],
            Action::NEITHER_FENCER_ID => [
                Call::ATTACK_ID => 0,
                Call::COUNTER_ATTACK_ID => 0,
                Call::RIPOSTE_ID => 0,
                Call::REMISE_ID => 0,
                Call::LINE_ID => 0,
                Call::OTHER_ID => 0,
                Call::SIMULTANEOUS_ID => 0,
                Call::CARD_ID => 0
            ]

        ];


        foreach ($this->getCallVotesAttribute() as $vote) {
            if ($vote->call_id !== null) {
                $calls[$vote->priority][$vote->call_id] += 1;
            }
            if ($vote->card_for !== null) {
                $calls[$vote->card_for][Call::CARD_ID] += 1;
            }
        }
        return $calls;
    }


    /**
     * Gets the name of the top vote
     *
     * @return string
     */
    public function getTopVoteNameAttribute()
    {
        $topVote = $this->getTopVoteAttribute();
        if ($topVote === false) {
            return '';
        }

        $callId = $this->getTopVoteAttribute()->callId;
        if ($callId === Call::CARD_ID) {
            return "Card";
        }

        $call =  Call::find($callId);

        if ($call !== null) {
            return $call->name;
        }

        return '';
    }


    /**
     * Returns top vote using cache
     *
     * @return null|object
     */
    public function getTopVoteAttribute()
    {
        return Cache::remember($this->cacheKey() . ':topVote', $this->cacheMinutes, function () {
            return $this->getTopVote();
        });
    }


    /**
     * Gets the higest vote with priorityId, callId and count
     *
     * @return boolean|object
     */
    public function getTopVote()
    {
        if (count($this->getCallVotesAttribute()) === 0) {
            return false;
        }

        $calls = $this->getCachedCallsArray();
        $highestVoteCount = -1;
        $highestCountVote = false;
        foreach ($calls as $priorityId => $priorityCalls) {
            foreach ($priorityCalls as $callId => $count) {
                if ($count > $highestVoteCount) {
                    $highestVoteCount = $count;
                    $highestCountVote = (object) [
                      'priorityId' => $priorityId,
                      'callId' => $callId,
                      'count' => $count,
                    ];
                }
            }
        }

        return $highestCountVote;
    }


    public function getIsNotActionAttribute()
    {
        $votes = $this->votes()->where('vote_comment_id', 2)->get();
        if (count($votes) > 1) {
            return true;
        }
        return false;
    }



    /**
     * Returns the call votes using a cache
     *
     * @return Collection
     */
    public function getAverageDifficultyRatingAttribute()
    {
        return Cache::remember($this->cacheKey() . ':averageDifficultyRating', $this->cacheMinutes, function () {
            return $this->getAverageDifficultyRating();
        });
    }

    public function getAverageDifficultyRating()
    {
        $votes = $this->votes()->whereNotNull('difficulty')->get();

        $voteCount = count($votes);
        if($voteCount === 0) {
            return false;
        }

        $totalDifficulty = 0;
        foreach ($votes as $vote) {
            $totalDifficulty += $vote->difficulty;
        }
        return $totalDifficulty / $voteCount;
    }



    /**
     * Returns the highest vote count using a cache
     *
     * @return integer
     */
    public function getHighestVoteCountAttribute()
    {
        return Cache::remember($this->cacheKey() . ':getHighestVoteCount', $this->cacheMinutes, function () {
            return $this->getHighestVoteCount();
        });
    }

    public function getHighestVoteCount()
    {
        $calls = $this->getCachedCallsArray();
        $highestVoteCount = -1;

        foreach ($calls as $priority => $priorityCalls) {
            foreach ($priorityCalls as $count) {
                if ($count > $highestVoteCount) {
                    $highestVoteCount = $count;
                }
            }
        }

        return $highestVoteCount;
    }


    public function getConfidenceAttribute()
    {
        $voteCount = count($this->getCallVotesAttribute());
        if ($voteCount === 0) {
            return false;
        }

        $highestVoteCount = $this->getHighestVoteCountAttribute();
        return Utility::calculateBinaryConfidenceInterval($highestVoteCount, $voteCount);
    }


    public function getConsensusAttribute()
    {
        $voteCount = count($this->getCallVotesAttribute());
        if ($voteCount === 0) {
            return false;
        }

        $highestVoteCount = $this->getHighestVoteCountAttribute();

        return $highestVoteCount / $voteCount;
    }


    /**
     * Returns the call votes using a cache
     *
     * @return Collection
     */
    public function getCallVotesAttribute()
    {
        return Cache::remember($this->cacheKey() . ':callVotes', $this->cacheMinutes, function () {
            return $this->getCallVotes();
        });
    }

    /**
     * Returns the votes which are part of call
     *
     * @return Collection
     */
    public function getCallVotes()
    {
        $votes = $this->votes()
            ->get();

        $returnCollection = new Collection();

        foreach ($votes as $vote) {
            if ($vote->call_id !== null
                || $vote->card_for !== null
            ) {
                $returnCollection->push($vote);
            }
        }

        return $returnCollection;
    }


    /**
     * Gets the Gfycat Id
     *
     * @return array
     */
    public function getVideoAttribute()
    {
        return $this->video_url;
    }


    /**
     * Gets the options for the right fencer field
     *
     * @return array
     */
    public function getBoutOptions()
    {
        if (isset($this->tournament) === true) {
            $tournament = Tournament::find($this->tournament->id);

            // Add an empty option to the beginning of the list
            return (
                [null => 'Unknown / Other']
                + Bout::where('tournament_id', '=', $tournament->id)
                    ->lists('cache_name', 'id')
            );
        }

        if (isset($this->bout) === true) {
            return (
                [null => 'Unknown / Other']
                + Bout::where('tournament_id', '=', $this->bout->tournament_id)
                    ->lists('cache_name', 'id')
            );
        }

        return ([null => "Select Tournament"] + [null => 'Unknown / Other']);
    }


    /**
     * Gets the options for the tournament field
     *
     * @return array
     */
    public function getTournamentOptions()
    {
        // Add an empty option to the beginning of the list
        return ([null => 'Unknown / Other'] + Tournament::all()->lists('fullname', 'id'));
    }


    /**
     * Gets the options for the right fencer field
     *
     * @return array
     */
    public function getLeftFencerOptions()
    {
        if (isset($this->bout) === true) {
            $leftFencer = Fencer::find($this->bout->left_fencer_id);

            // Populate from bout
            return [
                null => "-- " . $leftFencer->name . " --"
            ];
        }

        // Add an empty option to beginning of the list
        $list = ([null => 'Unknown / Other'] + Fencer::all()->lists('name', 'id'));
        return $list;
    }


    /**
     * Gets the options for the right fencer field
     *
     * @return array
     */
    public function getRightFencerOptions()
    {

        if (isset($this->bout) === true) {
            $rightFencer = Fencer::find($this->bout->right_fencer_id);

            // Populate from bout
            return [
                null => "-- " . $rightFencer->name . " --"
            ];
        }

        // Add an empty option to beginning of the list
        $list = ([null => 'Unknown / Other'] + Fencer::all()->lists('name', 'id'));
        return $list;
    }


    /**
     * Gets the name of the Left fencer
     *
     * @return string
     */
    public function getLeftnameAttribute()
    {
        if (isset($this->bout) === true) {
            $leftFencer = Fencer::find($this->bout->left_fencer_id);
        } else {
            $leftFencer = Fencer::find($this->left_fencer_id);
        }

        if ($leftFencer !== null) {
            return $leftFencer->name;
        }

        return '';
    }


    /**
     * Gets the name of the right fencer
     *
     * @return string
     */
    public function getRightnameAttribute()
    {
        if (isset($this->bout) === true) {
            $rightFencer = Fencer::find($this->bout->right_fencer_id);
        } else {
            $rightFencer = Fencer::find($this->right_fencer_id);
        }

        if ($rightFencer !== null) {
            return $rightFencer->name;
        }

        return '';
    }


    /**
     * Gets the name of the right fencer
     *
     * @return void
     */
    public function reverseFencers()
    {
        // If the action has a bout, then reverse the bout fencers
        if ($this->bout_id !== null) {
            $bout = Bout::find($this->bout_id);

            // Reverse the fencers in the parent bout
            $bout->reverseFencers();
        } else {
            $temp = $this->left_fencer_id;
            $this->left_fencer_id = $this->right_fencer_id;
            $this->right_fencer_id = $temp;
            $this->save();
        }
    }
}
