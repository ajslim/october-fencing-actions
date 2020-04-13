<?php namespace Ajslim\FencingActions\Models;

use Ajslim\FencingActions\Utility\Utility;
use Assetic\Filter\PackerFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Model;
use phpDocumentor\Reflection\Types\Integer;

/**
 * Action Model
 *
 * @mixin \Eloquent
 *
 * @propery integer left_fencer_id
 * @propery integer right_fencer_id
 * @propery Bout bout
 * @propery Collection votes
 * @propery float confidence_cache
 * @propery boolean is_verified_cache
 * @propery integer vote_count_cache
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


    public function updateCacheColumns()
    {
        $this->vote_count_cache = $this->getCallVotes()->count();
        $this->left_vote_count_cache = $this->votes()->where('priority', 1)->count();
        $this->right_vote_count_cache = $this->votes()->where('priority', 2)->count();

        $this->top_vote_name_cache = $this->getTopVoteName();
        $this->confidence_cache = $this->getConfidence();
        $this->consensus_cache = $this->getConsensus();
        $this->average_difficulty_cache = $this->getAverageDifficultyRating();
        $this->ordered_calls_cache = $this->getOrderedCallsString();
        $this->is_verified_cache = $this->getIsVerified();
        $this->verified_call_id_cache = $this->getVerifiedCallId();

        $this->left_fencer_id_cache = $this->bout->left_fencer_id;
        $this->right_fencer_id_cache = $this->bout->right_fencer_id;
        $this->tournament_id_cache = $this->bout->tournament_id;

        $this->save();
    }


    public function getVerifiedCallId()
    {
        /** @var Vote $verifiedVote */
        $verifiedVote = $this->getVerifiedVote();
        if ($verifiedVote !== false) {
            return $verifiedVote->call_id;
        }

        return null;
    }


    public function getOrderedCallsString()
    {
        $orderedCalls = $this->getOrderedCallsArray();
        $orderedCallsString = '';

        foreach ($orderedCalls as $call) {
            // If there is any vote count
            if ($call[2] > 0) {
                // {priority}:{call_id}:{count},
                $orderedCallsString .= $call[0] . ':' . $call[1] . ':' . $call[2] . ',';
            }
        }

        return $orderedCallsString;
    }

    public function getOrderedCallsArrayAttribute()
    {
        return Cache::remember($this->cacheKey() . ':orderedCalls', $this->cacheMinutes, function () {
            $this->ordered_calls_cache = $this->getOrderedCallsString();
            $this->save();
            return $this->getOrderedCallsArray();
        });
    }


    public function getOrderedCallsArray()
    {
        $calls = $this->getCallsArray();

        $orderVotes = [];
        foreach ($calls as $priorityId => $priorityCalls) {
            foreach ($priorityCalls as $callId => $callCount) {
                $orderVotes[] = [$priorityId, $callId, $callCount];
            }
        }

        usort($orderVotes, function ($a, $b) {
            return $b[2] - $a[2];
        });

        return $orderVotes;
    }


    /**
     * Returns the verified vote, or false
     *
     * @return boolean | Vote
     */
    public function getIsVerifiedAttribute()
    {
        return Cache::remember($this->cacheKey() . ':isVerified', $this->cacheMinutes, function () {
            $isVerified = $this->getIsVerified();
            $this->is_verified_cache = $isVerified;
            $this->save();
            return $isVerified;
        });
    }


    /**
     * Returns if verified
     *
     * @return boolean
     */
    public function getIsVerified()
    {
        $isVerified = false;
        if ($this->getVerifiedVote() !== false) {
            $isVerified = true;
        }
        return $isVerified;
    }


    /**
     * Returns the verified vote, or false
     *
     * @return boolean | Vote
     */
    public function getVerifiedVoteAttribute()
    {
        return Cache::remember($this->cacheKey() . ':verifiedCall', $this->cacheMinutes, function () {
            return $this->getVerifiedVote();
        });
    }


    /**
     * A call can be verified by an FIE ref, or by having a number of verifiers agree with
     * the majority with a certain confidence value
     *
     * @return bool|Vote
     */
    public function getVerifiedVote()
    {
        $fieConsensus = $this->getFieConsensusVote();
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
            ->where('call_id', '!=', null)
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
        return Cache::remember($this->cacheKey() . ':fieDifficultyFloor', $this->cacheMinutes, function () {
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
                $lowest = $fieVote->difficulty;
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
        return Cache::remember($this->cacheKey() . ':topVoteName', $this->cacheMinutes, function () {
            $this->top_vote_name_cache = $this->getTopVoteName();
            $this->save();
            return $this->top_vote_name_cache;
        });
    }


    /**
     * Gets the name of the top vote
     *
     * @return string
     */
    public function getTopVoteName()
    {
        $topVote = $this->getTopVote();
        if ($topVote === false) {
            return '';
        }

        $callId = $this->getTopVote()->callId;
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
     * Returns the verified vote
     */
    public function getVerifiedOrTopCall()
    {
        /** @var Vote $verifiedVote */
        $verifiedVote = $this->getVerifiedVote();
        if ($verifiedVote !== false) {
            return (object) [
                'priorityId' => $verifiedVote->priority,
                'callId' => $verifiedVote->call_id,
            ];
        }

        $topVote = $this->getTopVote();
        if ($topVote !== false) {
            return $topVote;
        }

        return false;
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
     * @return Float
     */
    public function getAverageDifficultyRatingAttribute()
    {
        return Cache::remember($this->cacheKey() . ':averageDifficultyRating', $this->cacheMinutes, function () {
            $this->average_difficulty_cache = $this->getAverageDifficultyRating();
            $this->save();
            return $this->average_difficulty_cache;
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
        return Cache::remember($this->cacheKey() . ':highestVoteCount', $this->cacheMinutes, function () {
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


    /**
     * Returns the confidence value
     *
     * @return float
     */
    public function getConfidenceAttribute()
    {
        return Cache::remember($this->cacheKey() . ':confidence', $this->cacheMinutes, function () {
            $this->confidence_cache = $this->getConfidence();
            $this->save();
            return $this->confidence_cache;
        });
    }

    public function getConfidence()
    {
        $voteCount = count($this->getCallVotesAttribute());
        if ($voteCount === 0) {
            return false;
        }

        $highestVoteCount = $this->getHighestVoteCountAttribute();
        return Utility::calculateBinaryConfidenceInterval($highestVoteCount, $voteCount);
    }


    /**
     * Returns the confidence value
     *
     * @return float
     */
    public function getConsensusAttribute()
    {
        return Cache::remember($this->cacheKey() . ':consensus', $this->cacheMinutes, function () {
            $this->consensus_cache = $this->getConsensus();
            $this->save();
            return $this->consensus_cache;
        });
    }


    public function getConsensus()
    {
        $voteCount = count($this->getCallVotesAttribute());
        if ($voteCount === 0) {
            return false;
        }

        $highestVoteCount = $this->getHighestVoteCountAttribute();

        return $highestVoteCount / $voteCount;
    }



    /**
     * Returns the vote count
     *
     * @return Integer
     */
    public function getVoteCountAttribute()
    {
        return Cache::remember($this->cacheKey() . ':voteCount', $this->cacheMinutes, function () {
            $this->vote_count_cache = count($this->getCallVotes());
            $this->save();
            return $this->vote_count_cache;
        });
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


    public function isEasyVerified()
    {
        if ($this->confidence_cache > 0.8 && $this->getIsVerified() === true) {
            return true;
        }

        return false;
    }

    public function isMediumVerified()
    {
        if ($this->confidence_cache > 0.7
            && $this->getIsVerified() === true
            && $this->consensus_cache < 1
        ) {
            return true;
        }

        return false;
    }

    public function isDifficultVerified()
    {
        if ($this->confidence_cache < 0.5
            && $this->getIsVerified() === true
            && $this->vote_count_cache > 3
        ) {
            return true;
        }

        return false;
    }

    public function getVoteArray()
    {
        $voteArray = [];

        $leftRightNeitherNames = [
            Action::LEFT_FENCER_ID => 'left',
            Action::RIGHT_FENCER_ID => 'right',
            Action::NEITHER_FENCER_ID => 'neither'
        ];

        $callsArray = $this->getCachedCallsArray();
        foreach ($callsArray as $leftRightNeitherId => $leftRightNeither) {

            $voteArray[$leftRightNeitherNames[$leftRightNeitherId]] = [];
            foreach ($leftRightNeither as $callId => $callCount) {
                $call = Call::find($callId);

                if ($call !== null) {
                    $voteArray[$leftRightNeitherNames[$leftRightNeitherId]][$call->name] = $callCount;
                }
            }
        }
        $voteArray['simultaneous'] = $callsArray[Action::NEITHER_FENCER_ID][Call::SIMULTANEOUS_ID];

        $voteArray['cardLeft'] = $callsArray[Action::LEFT_FENCER_ID][Call::CARD_ID];
        $voteArray['cardRight'] = $callsArray[Action::RIGHT_FENCER_ID][Call::CARD_ID];

        $voteArray['totalPriority'] = $this->votes()->whereNotNull('priority')->count();
        $voteArray['totalCards'] = $this->votes()->whereNotNull('card_for')->count();
        $voteArray['total'] = $this->getCallVotesAttribute()->count();

        return $voteArray;
    }
}
