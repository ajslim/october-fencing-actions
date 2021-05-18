<?php namespace Ajslim\FencingActions\Repositories;

use Ajslim\FencingActions\Models\Action;

class ActionRepository {
    public static function getActions()
    {
        return Action::whereDoesntHave('votes', function ($query) {
            $query->where('vote_comment_id', 2);
        })->inRandomOrder();
    }

    public static function getDifficultActions()
    {
        return Action::where('vote_count_cache', '>', 3)
            ->where('confidence_cache', '<', 0.5)
            ->inRandomOrder();
    }


    public static function getDifficultUnverifiedActions()
    {
        return Action::where('confidence_cache', '<', '0.5')
            ->where('is_verified_cache', '!=', '1')
            ->inRandomOrder();
    }


    public static function getActionsWithNoVotes()
    {
        return Action::where('vote_count_cache', '=', 0)
            ->orWhere('vote_count_cache', '=', null)
            ->inRandomOrder();
    }

    public static function getActionsWithFewVotes()
    {
        return Action::where('vote_count_cache', '<', 3)
            ->orWhere('vote_count_cache', '=', null)
            ->where('is_verified_cache', '!=', '1')
            ->inRandomOrder();
    }


    public static function getEasyVerifiedActions()
    {
        return Action::where('confidence_cache', '>', '0.8')
            ->where('is_verified_cache', '=', '1')->inRandomOrder();
    }


    public static function getMediumVerifiedActions()
    {
        return Action::where('confidence_cache', '>', '0.6')
            ->where('confidence_cache', '<', '0.8')
            ->where('is_verified_cache', '=', '1')->inRandomOrder();
    }


    public static function getDifficultVerifiedActions()
    {
        return Action::where('confidence_cache', '<', '0.5')
            ->where('vote_count_cache', '>', '3')
            ->where('is_verified_cache', '=', '1')->inRandomOrder();
    }


    public static function getVerifiedActions()
    {
        return Action::where('is_verified_cache', '1')->inRandomOrder();
    }
}
