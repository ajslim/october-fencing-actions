<?php namespace Ajslim\FencingActions\Models;

use Ajslim\FencingActions\Utility\Utility;
use Assetic\Filter\PackerFilter;
use Illuminate\Support\Collection;
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
 * @method votes
 */
class Action extends Model
{
    protected $connection = 'business';

    public const NEITHER_FENCER_ID = 0;
    public const LEFT_FENCER_ID = 1;
    public const RIGHT_FENCER_ID = 2;

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


    private function getCallsArray()
    {
        $calls = [
            Call::ATTACK_ID => [0, 0, 0],
            Call::COUNTER_ATTACK_ID => [0, 0, 0],
            Call::RIPOSTE_ID => [0, 0, 0],
            Call::REMISE_ID => [0, 0, 0],
            Call::LINE_ID => [0, 0, 0],
            Call::OTHER_ID => [0, 0, 0],
            Call::SIMULTANEOUS_ID => [0, 0, 0],
            Call::CARD_ID => [0, 0 ,0]
        ];
        foreach ($this->getCallVotesAttribute() as $vote) {
            if ($vote->call !== null) {
                $calls[$vote->call->id][$vote->priority] += 1;
            }
            if ($vote->card_for !== null) {
                $calls[Call::CARD_ID][$vote->card_for] += 1;
            }
        }
        return $calls;
    }


    public function getTopVoteNameAttribute()
    {
        $voteId = $this->getTopVoteIdAttribute();

        if ($voteId === null) {
            return '';
        }

        if ($voteId === Call::CARD_ID) {
            return "Card";
        }

        $call =  Call::find($voteId);

        if ($call !== null) {
            return $call->name;
        }

        return '';
    }


    public function getTopVoteIdAttribute()
    {
        if (count($this->getCallVotesAttribute()) === 0) {
            return null;
        }

        $calls = $this->getCallsArray();
        $highestVoteCount = -1;
        $highestCountCallId = 0;
        foreach ($calls as $callId => $votes) {
            $total = array_sum($votes);
            if ($total > $highestVoteCount) {
                $highestVoteCount = $total;
                $highestCountCallId = $callId;
            }
        }

        return $highestCountCallId;
    }


    public function getIsNotActionAttribute()
    {
        $votes = $this->votes()->where('vote_comment_id', 2)->get();
        if (count($votes) > 1) {
            return true;
        }
        return false;
    }


    public function getAverageDifficultyRatingAttribute()
    {
        $votes = $this->votes()->whereNotNull('difficulty')->get();

        $voteCount = count($votes);
        if($voteCount === 0) {
            return null;
        }

        $totalDifficulty = 0;
        foreach ($votes as $vote) {
            $totalDifficulty += $vote->difficulty;
        }
        return $totalDifficulty / $voteCount;
    }


    public function getHighestVoteCountAttribute()
    {
        $calls = $this->getCallsArray();
        $highestVoteCount = -1;

        foreach ($calls as $callId => $priority) {
            foreach ($priority as $count) {
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
            return null;
        }

        $highestVoteCount = $this->getHighestVoteCountAttribute();
        return Utility::calculateBinaryConfidenceInterval($highestVoteCount, $voteCount);
    }



    public function getConsensusAttribute()
    {
        $voteCount = count($this->getCallVotesAttribute());
        if ($voteCount === 0) {
            return null;
        }

        $highestVoteCount = $this->getHighestVoteCountAttribute();

        return $highestVoteCount / $voteCount;
    }


    public function getCallVotesAttribute()
    {
        $votes = $this->votes()
            ->get();


        $returnCollection = new Collection();

        foreach ($votes as $vote) {
            if ($vote->call !== null
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
