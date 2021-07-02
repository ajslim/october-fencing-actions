<?php namespace Ajslim\Fencingactions\Html;

use Ajslim\FencingActions\Models\Bout;
use Cms\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;

class MadLibBouts extends Controller
{
    public function index()
    {
        $actionCount = 6;

        $bout = Bout::whereHas('actions', function($actionsQuery) {
           $actionsQuery->where('top_vote_name_cache', '!=', '')
               ->where('top_call_cache', '!=', 'unknown / other');
           $actionsQuery;
        }, '>=', $actionCount)->inRandomOrder()->first();

        $actions = $bout->actions()
            ->where('top_call_cache', '!=', '')
            ->where('top_call_cache', '!=', 'unknown / other')
            ->inRandomOrder()
            ->take($actionCount)
            ->get();

        $actions = $actions->sortBy('time');

        $leftFencer = $bout->left_fencer;
        $rightFencer = $bout->right_fencer;

        $leftScoreCount = 0;
        $leftStreakCount = 0;
        $rightScoreCount = 0;
        $rightStreakCount = 0;

        echo $bout->name;
        echo $actions->count() . " - ";

        echo "<br />";
        echo "<br />";

        echo $this->getBoutBackground($bout);
        echo "<br />";

        $actionCount = 0;
        $lastScoringFencer = null;
        foreach($actions as $action) {
            $actionCount += 1;
            $scoringFencerIndex = substr($action->top_call_cache,0,1);

//            echo '<video id="video" controls="" loop="" autoplay="true" playsinline="" preload="auto" tabindex="-1">
//                <source src="' . $action->video_url . '" type="video/mp4">
//            </video>';

            if ($scoringFencerIndex === '2') {
                $scoringFencer = $rightFencer;
                $losingFencer = $leftFencer;
                $rightScoreCount += 1;
                $rightStreakCount += 1;
                $leftStreakCount = 0;
            } else {
                $scoringFencer = $leftFencer;
                $losingFencer = $rightFencer;
                $leftScoreCount += 1;
                $leftStreakCount += 1;
                $rightStreakCount = 0;
            }

            echo '<br/><br/>';

            if ($leftStreakCount >= 3) {
                echo $this->getStreakText($leftFencer, $rightFencer, $leftStreakCount);
            } else if ($rightStreakCount >= 3) {
                echo $this->getStreakText($rightFencer, $leftFencer, $rightStreakCount);
            }


            if ($actionCount === 1) {
                echo $this->getFirstActionPreface();
                echo $this->getRandomFencerIdentifier($scoringFencer)
                    . ' starts off and ';
            } else {

                if ($leftStreakCount < 3 && $rightStreakCount < 3) {
                    echo $this->getFluffText($scoringFencer, $losingFencer);
                }

                echo $this->getFollowUpActionPreface($scoringFencer, $lastScoringFencer);
                echo 'He';
            }

            echo $this->getRandomActionDeliveryText()
                . $this->getRandomAdjective()
                . $this->getSingleLightQualifier($action)
                . strtolower($action->top_vote_name_cache)
                . $this->getScoringOrOffTargetText($action);

            $lastScoringFencer = $scoringFencer;


            echo '<br/><br/>';
        }



        die;
    }


    private function getRandomFencerIdentifier($fencer)
    {
        $random = rand(1, 4);
        if ($random < 2) {
            return $fencer->first_name;
        } else {
            return studly_case(strtolower($fencer->last_name));
        }
    }


    private function getRandomAdjective()
    {
        $random = rand(1, 8);
        if ($random < 2) {
            return 'strong ';
        } elseif ($random < 3) {
            return 'precise ';
        } elseif ($random < 4) {
            return 'skillful ';
        } elseif ($random < 5) {
            return 'solid ';
        } elseif ($random < 6) {
            return 'quick ';
        }

        return '';
    }

    private function getSingleLightQualifier($action)
    {
        $index = substr($action->top_call_cache,0,1);

        if ($index === '1') {
            if ($action->left_coloured_light === 1 && $action->right_coloured_light !== 1) {
                return 'single light ';
            }
        }

        if ($index === '2') {
            if ($action->left_coloured_light !== 1 && $action->right_coloured_light === 1) {
                return 'single light ';
            }
        }
    }


    private function getBoutBackground($bout)
    {
        $boutDate = $bout->tournament->start_date;
        $boutBackground = '';

        $leftFencerWinCount = 0;
        $rightFencerWinCount = 0;

        $leftFencerId = $bout->left_fencer_id;
        $rightFencerId = $bout->right_fencer_id;

        $previousBouts = Bout::where(function($query) use ($leftFencerId, $rightFencerId) {
            $query->where('left_fencer_id', $leftFencerId)
                ->where('right_fencer_id', $rightFencerId);

            })
            ->orWhere(function($query) use ($leftFencerId, $rightFencerId) {
                $query->where('right_fencer_id', $leftFencerId)
                    ->where('left_fencer_id', $rightFencerId);

            })->get();

        if (count($previousBouts) === 1) {
            $previousBout = $previousBouts->first();
            $boutBackground .= ' These fencers have fenced once before in '
                . $previousBout->tournament->place
                . ' in '
                . substr($previousBout->tournament->start_date, 0, 4)
                . '.';

            $boutBackground .= $this->getPreviousBoutWinLoseDescription($previousBout);

            if ($previousBout->left_score > $previousBout->right_score) {
                $leftFencerWinCount += 1;
            } else {
                $rightFencerWinCount += 1;
            }
        } elseif (count($previousBouts) > 1) {
            $boutBackground .= ' These fencers have fenced a few times before. ';
            $count = 0;
            foreach ($previousBouts as $previousBout) {
                $count += 1;
                if ($count > 1) {
                    $boutBackground .= ' Then again in '
                        . $previousBout->tournament->place
                        . ' in '
                        . substr($previousBout->tournament->start_date, 0, 4)
                        . '.';

                        $boutBackground .= $this->getPreviousBoutWinLoseDescription($previousBout);
                } else {
                    $boutBackground .= ' First in '
                        . substr($previousBout->tournament->start_date, 0, 4)
                        . ' in '
                        . $previousBout->tournament->place
                        . '.';
                    $boutBackground .= $this->getPreviousBoutWinLoseDescription($previousBout);
                }

                if ($previousBout->left_score > $previousBout->right_score) {
                    $leftFencerWinCount += 1;
                } else {
                    $rightFencerWinCount += 1;
                }
            }
        } else {
            $boutBackground .= ' These fencers have never fenced at this point.';
        }

        if ($leftFencerWinCount > $rightFencerWinCount) {
            $boutBackground .= ' So it\'s up to '
                .  $this->getRandomFencerIdentifier($previousBout->right_fencer)
                . ' to find a way to turn this around.';
        } elseif ($leftFencerWinCount < $rightFencerWinCount) {
            $boutBackground .= ' So we will see if '
                .  $this->getRandomFencerIdentifier($previousBout->right_fencer)
                . ' can continue this trend.';
        } else {
            $boutBackground .= ' So odds are good for both fencers going into this bout.';
        }

        return $boutBackground;
    }

    private function getFirstActionPreface()
    {
        $random = rand(1, 5);
        $text =  'The first action we\'re going to look at frames the entire bout. ';
        if ($random < 2) {
            $text = 'This opening action sets the tone for the rest of the bout. ';
        } elseif ($random < 3) {
            $text = ' Starting off lets look at the following action. ';
        }

        return $text;
    }

    /**
     * @param        $previousBout
     * @param string $boutBackground
     * @return string
     */
    private function getPreviousBoutWinLoseDescription($previousBout)
    {
        $description = ' ' . $this->getRandomFencerIdentifier($previousBout->left_fencer);
        if ($previousBout->left_score > $previousBout->right_score) {
            $description .= ' won this bout ' .
                $previousBout->left_score . '-' . $previousBout->right_score
                . '.';
        } else {
            $description .= ' lost this bout '
                . $previousBout->right_score . '-' . $previousBout->left_score
                . '.';
        }
        return $description;
    }


    private function getRandomActionDeliveryText()
    {
        $random = rand(1, 5);
        if ($random < 2) {
            return ' drives his ';
        } elseif ($random < 3) {
            return ' controls the distance well to hit a ';
        } elseif ($random < 4) {
            return ' uses good tempo to deliver a ';
        }

        return ' makes a ';
    }

    /**
     * @param $action
     * @return false|string
     */
    private function getScoringOrOffTargetText($action)
    {
        $index = substr($action->top_call_cache, 0, 1);

        $text = '';
        if ($index === '1') {
            if ($action->left_coloured_light === 1) {
                $text .= $this->getScoringText();
            } else {
                $text .= $this->getOffTargetText();
            }
        }

        if ($index === '2') {
            if ($action->right_coloured_light === 1) {
                $text .= $this->getScoringText();
            } else {
                $text .= $this->getOffTargetText();
            }
        }
        return $text;
    }


    private function getFollowUpActionPreface($scoringFencer, $lastScoringFencer)
    {
        $text = '';
        if ($scoringFencer->id !== $lastScoringFencer->id) {
            $text = $this->getScoringFencerChangedText($scoringFencer, $text);
        } else {
            $text = $this->getScoringFencerRemainsTheSameText($scoringFencer, $text);
        }

        return $text;
    }

    /**
     * @param        $scoringFencer
     * @param string $text
     * @return string
     */
    private function getScoringFencerChangedText($scoringFencer, string $text): string
    {
        $random = rand(1, 5);
        $text = $this->getRandomFencerIdentifier($scoringFencer) . ' comes up with an answer. ';
        if ($random < 2) {
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' needs to find a way to respond. So, ';
        } elseif ($random < 3) {
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' finds a way to change it up. ';
        } elseif ($random < 4) {
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' hits back. ';
        }

        return $text;
    }

    /**
     * @param        $scoringFencer
     * @param string $text
     * @return string
     */
    private function getScoringFencerRemainsTheSameText($scoringFencer, string $text): string
    {
        $random = rand(1, 5);
        $text .= $this->getRandomFencerIdentifier($scoringFencer) . ' continues building his streak. ';
        if ($random < 2) {
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' converts his last success into another action.';
        } elseif ($random < 3) {
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' comes in strong again. ';
        } elseif ($random < 4) {
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' builds on his previous success. ';
        }

        return $text;
    }


    private function getScoringText()
    {
        $random = rand(1, 5);
        $text = ' that scores.';
        if ($random < 2) {
            $text = ' for a point.';
        } elseif ($random < 3) {
            $text = ' for a touche.';
        } elseif ($random < 4) {
            $text = ' bringing him one point closer.';
        }

        return $text;
    }


    /**
     * @param string $text
     * @return string
     */
    private function getOffTargetText()
    {
        $random = rand(1, 5);
        $text = ' but lands off target.';
        if ($random < 2) {
            $text = ' narrowly missing the target.';
        } elseif ($random < 3) {
            $text = ' just barely missing.';
        } elseif ($random < 4) {
            $text = ' hitting off target.';
        }

        return $text;
    }


    /**
     * @param string $text
     * @return string
     */
    private function getStreakText($streakFencer, $losingFencer, $streakLength)
    {
        if ($streakLength === 3) {
            $random = rand(1, 3);
            $text = $this->getRandomFencerIdentifier($streakFencer)
                . ' has now made ' . $streakLength . 'actions in a row.'
                . $this->getRandomFencerIdentifier($losingFencer)
                . ' needs to find a way to take some control in the bout. ';

            if ($random < 2) {
                $text = $this->getRandomFencerIdentifier($streakFencer)
                    . ' is controlling the bout. '
                    . $this->getRandomFencerIdentifier($losingFencer)
                    . ' needs to find a way to push back. ';
            } elseif ($random < 3) {
                $text = $this->getRandomFencerIdentifier($losingFencer)
                    . ' is on the back foot after '
                    . $streakLength
                    . ' actions from '
                    . $this->getRandomFencerIdentifier($streakFencer)
                    . '. ';
            }
        } else {
            $random = rand(1, 3);
            $text = $this->getRandomFencerIdentifier($streakFencer) . '\'s streak continues. ';
            if ($random < 2) {
                $text = $this->getRandomFencerIdentifier($losingFencer) . ' is still struggling to find push back. ';
            } elseif ($random < 3) {
                $text = $this->getRandomFencerIdentifier($streakFencer) . ' is able to continue dictating the bout. ';
            }
        }

        return $text;
    }


    private function getFluffText($scoringFencer, $losingFencer)
    {


            $random = rand(1, 3);
            $text = 'The next action shows very good distance control from '
                . $this->getRandomFencerIdentifier($scoringFencer)
                . '. He uses careful footwork to manipulate '
                . $this->getRandomFencerIdentifier($losingFencer)
                . ' into a position that gives him a good chance to score. ';


            if ($random < 2) {
                $text = $this->getRandomFencerIdentifier($scoringFencer)
                    . ' shows strong athleticism on this action, and he\'s able to get the better of '
                    . $this->getRandomFencerIdentifier($losingFencer)
                    . '. ';
            } elseif ($random < 3) {
                $text = '';
            }

        return $text;
    }
}
