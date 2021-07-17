<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CreateMadlibsClips extends Command
{
    private $force = false;

    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:createmadlibsclips';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    public function handle()
    {
        for ($x = 0; $x <= 100; $x++) {
            $outputText = [];
            $fileNames = [];

            $actionCount = 6;

            $actionTypeCount = [
                'left' => [
                    'attack' => 0,
                    'counter attack' => 0,
                    'riposte' => 0,
                    'remise' => 0,
                ],
                'right' => [
                    'attack' => 0,
                    'counter attack' => 0,
                    'riposte' => 0,
                    'remise' => 0,
                ]
            ];

            $folder = './storage/temp/public/madlibclips';

            // Clear the folder
            system('mv ' . $folder . '/output.mp4 ' . $folder . '/output.save');
            system('rm -f ' . $folder . '/*.mp4');
            system('rm -f ' . $folder . '/*.txt');
            system('mv ' . $folder . '/output.save ' . $folder . '/output.mp4');

            $bout = Bout::whereHas('actions', function ($actionsQuery) {
                $actionsQuery->where('top_vote_name_cache', '!=', '')
                    ->where('top_vote_name_cache', '!=', 'Unknown / Other')
                    ->where('top_vote_name_cache', '!=', 'Simultaneous')
                    ->where('top_vote_name_cache', '!=', 'Line')
                    ->where('top_vote_name_cache', '!=', 'Card');
            }, '>=', $actionCount)->inRandomOrder()->first();

            $actions = $bout->actions()
                ->where('top_vote_name_cache', '!=', '')
                ->where('top_vote_name_cache', '!=', 'Unknown / Other')
                ->where('top_vote_name_cache', '!=', 'Simultaneous')
                ->where('top_vote_name_cache', '!=', 'Line')
                ->where('top_vote_name_cache', '!=', 'Card')
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

            $lineLength = 13;

            $boutBackground = $this->getBoutBackground($bout);
            $outputText[] = $boutBackground;
            $boutBackground = $this->addLineBreaks($boutBackground, $lineLength);

            $this->createTextOverlay('start.mp4', $folder, $boutBackground);
            $fileNames[] = $folder . '/overlay-start.mp4';

            $actionCount = 0;
            $actionText = '';
            $lastScoringFencer = null;
            foreach ($actions as $action) {
                $actionCount += 1;
                $scoringFencerIndex = substr($action->top_call_cache, 0, 1);

                if ($scoringFencerIndex === '2') {
                    $scoringFencerSide = 'left';
                    $scoringFencer = $rightFencer;
                    $losingFencer = $leftFencer;
                    $rightScoreCount += 1;
                    $rightStreakCount += 1;
                    $leftStreakCount = 0;
                } else {
                    $scoringFencerSide = 'right';
                    $scoringFencer = $leftFencer;
                    $losingFencer = $rightFencer;
                    $leftScoreCount += 1;
                    $leftStreakCount += 1;
                    $rightStreakCount = 0;
                }

                if (isset($actionTypeCount[$scoringFencerSide][strtolower($action->top_vote_name_cache)])) {
                    $actionTypeCount[$scoringFencerSide][strtolower($action->top_vote_name_cache)] += 1;
                }

                if ($leftStreakCount >= 3) {
                    $actionText .= $this->getStreakText($leftFencer, $rightFencer, $leftStreakCount) . "\n";
                } else if ($rightStreakCount >= 3) {
                    $actionText .= $this->getStreakText($rightFencer, $leftFencer, $rightStreakCount) . "\n";
                }

                if (isset($actionTypeCount[$scoringFencerSide][strtolower($action->top_vote_name_cache)])) {
                    if ($actionTypeCount[$scoringFencerSide][strtolower($action->top_vote_name_cache)] > 2) {
                        $actionText .= $this->getRepeatedActionText($scoringFencer, $losingFencer, $action->top_vote_name_cache) . "\n";
                    }
                }

                if ($actionCount === 1) {
                    $actionText .= $this->getFirstActionPreface();
                    $actionText .= $this->getRandomFencerIdentifier($scoringFencer)
                        . ' starts off and ';
                } else {
                    if ($leftStreakCount < 3 && $rightStreakCount < 3) {
                        $random = rand(1, 2);
                        if ($random < 2) {
                            $actionText .= $this->getFluffText($scoringFencer, $losingFencer);
                        } else {
                            $actionText .= $this->getFollowUpActionPreface($scoringFencer, $lastScoringFencer);
                        }
                    }

                    $actionText .= $this->getRandomFencerIdentifier($scoringFencer);
                }

                $actionText .= $this->getRandomActionDeliveryText()
                    . $this->getRandomAdjective()
                    . $this->getSingleLightQualifier($action)
                    . strtolower($action->top_vote_name_cache)
                    . $this->getScoringOrOffTargetText($action);

                $lastScoringFencer = $scoringFencer;


                $videoPathArray = explode('/', $action->video_url);
                $videoName = end($videoPathArray);

                $this->createTitle($videoName, $folder, 'Action ' . $actionCount);
                $fileNames[] = $folder . '/title-' . $videoName;

                system('echo "file \'' . $videoName . '\'" >> ' . $folder . '/list.txt');
                system('cp .' . $action->video_url . ' ' . $folder . '/');
                $fileNames[] = $folder . '/' . $videoName;

                $actionTextNewLines = $this->addLineBreaks($actionText, $lineLength);
                $this->createTextOverlay($videoName, $folder, $actionTextNewLines);
                $fileNames[] = $folder . '/overlay-' . $videoName;


                $outputText[] = $actionText;
                $actionText = '';
            }


            $closingStatement = $this->getClosingStatement($bout, $actionTypeCount);
            $outputText[] = $closingStatement;
            $closingStatement = $this->addLineBreaks($closingStatement, $lineLength);

            $this->createTextOverlay('end.mp4', $folder, $closingStatement);
            $fileNames[] = $folder . '/overlay-end.mp4';

            $inputString = '';
            foreach ($fileNames as $fileName) {
                $inputString .= ' -i ' . $fileName;
            }

            $cmd = 'ffmpeg ' . $inputString . ' -filter_complex "[0:v] [1:v] [2:v]  concat=n=' . count($fileNames) . ':v=1 [v]" -map "[v]" ' . $folder . '/input.mp4';
            system($cmd);

            system('cp backgroundmusic.mp3 ' . $folder . '/');
            $id = time();
            $cmd = 'ffmpeg -i ' . $folder . '/input.mp4 -i backgroundmusic.mp3 -c:v copy -map 0:v:0 -map 1:a:0 -c:a aac -b:a 192k -shortest ' . $folder . '/' . $id . '.mp4';

            system($cmd);

            $folder2 = './storage/madlibs';

            // Copy to output
            system('cp ' . $folder . '/' . $id . '.mp4 ' . $folder2 . '/output-' . $x . '.mp4');
        }
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

        $boutBackground .= 'This is an analysis of a bout between '
            . $bout->left_fencer->first_name
            . ' '
            . ucfirst(strtolower($bout->left_fencer->last_name))
            . ' and '
            . $bout->right_fencer->first_name
            . ' '
            . ucfirst(strtolower($bout->right_fencer->last_name))
            . ' in '
            . substr($boutDate, 0, 4)
            . '. ';


        $previousBouts = Bout::where(function($andquery) use ($leftFencerId, $rightFencerId) {
            $andquery->where(function ($query) use ($leftFencerId, $rightFencerId) {
                $query->where('left_fencer_id', $leftFencerId)
                    ->where('right_fencer_id', $rightFencerId);

            })
                ->orWhere(function ($query) use ($leftFencerId, $rightFencerId) {
                    $query->where('right_fencer_id', $leftFencerId)
                        ->where('left_fencer_id', $rightFencerId);

                });
        })
            ->whereHas('tournament', function($query) use ($boutDate) {
                $query->where('start_date', '<', $boutDate);
            })
            ->get();

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
            $boutBackground .= ' So odds are equally good for both fencers going into this bout.';
        }

        return $boutBackground;
    }

    private function getFirstActionPreface()
    {
        $random = rand(1, 5);
        $text =  'This first action frames the entire bout. ';
        if ($random < 2) {
            $text = 'This opening action sets the tone for the rest of the bout. ';
        } elseif ($random < 3) {
            $text = 'The first action sets the theme of the bout to come. ';
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
            $text = $this->getRandomFencerIdentifier($scoringFencer) . ' converts his last success into another action. ';
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
            $text = ' bringing him one point closer to victory.';
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
                . ' has now made ' . $streakLength . ' actions in a row. '
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


    /**
     * @param string $text
     * @return string
     */
    private function getRepeatedActionText($streakFencer, $losingFencer, $action)
    {
        $random = rand(1, 3);
        $text = 'Yet another ' . $action . ' from ' . $this->getRandomFencerIdentifier($streakFencer)
            . '. ';
        if ($random < 2) {
            $text = $this->getRandomFencerIdentifier($streakFencer)
                . '\'s ' . $action . 's are his main source of points. ';
        } elseif ($random < 3) {
            $text = $this->getRandomFencerIdentifier($losingFencer)
                . ' is hit by another '
                . $action
                . '. ';
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

    /**
     * @param string $actionText
     * @return string
     */
    private function addLineBreaks(string $actionText, $length): string
    {
        $actionTextWords = explode(' ', $actionText);
        $actionTextNewLines = '';
        foreach ($actionTextWords as $count => $actionTextWord) {
            if ($count % $length === $length-1) {
                $actionTextNewLines .= "\n";
            }
            $actionTextNewLines .= $actionTextWord . ' ';
        }

        // Replace a attack to an attack
        str_replace(' a a', ' an a', $actionTextNewLines);
        str_replace(' a e', ' an e', $actionTextNewLines);
        str_replace(' a i', ' an i', $actionTextNewLines);
        str_replace(' a o', ' an o', $actionTextNewLines);
        str_replace(' a u', ' an u', $actionTextNewLines);

        return $actionTextNewLines;
    }

    /**
     * @param        $videoName
     * @param string $folder
     * @param string $drawTextSettings
     * @param string $actionTextNewLines
     */
    private function createTextOverlay($videoName, string $folder, string $actionTextNewLines): void
    {
        $drawTextSettings =  'color=size=640x360:duration=8:color=black -vf "drawtext=fontsize=14:line_spacing=16:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2:';
        system('ffmpeg -f lavfi -i ' . $drawTextSettings . 'text=\'' . $actionTextNewLines . '\'" ' . $folder . '/overlay-' . $videoName);
    }


    /**
     * @param        $videoName
     * @param string $folder
     * @param string $drawTextSettings
     * @param string $actionTextNewLines
     */
    private function createTitle($videoName, string $folder, string $actionTextNewLines): void
    {
        $drawTextSettings =  'color=size=640x360:duration=4:color=black -vf "drawtext=fontsize=20:line_spacing=16:fontcolor=white:x=(w-text_w)/2:y=(h-text_h)/2:';
        system('ffmpeg -f lavfi -i ' . $drawTextSettings . 'text=\'' . $actionTextNewLines . '\'" ' . $folder . '/title-' . $videoName);
    }



    private function getClosingStatement($bout, $actionTypeCount)
    {

        if ($bout->left_score > $bout->right_score) {
            $winningSide = 'left';
            $winningFencer = $bout->left_fencer;
            $losingFencer = $bout->right_fencer;
        } else {
            $winningSide = 'right';
            $winningFencer = $bout->right_fencer;
            $losingFencer = $bout->left_fencer;
        }

        $actions = $actionTypeCount[$winningSide];
        arsort($actions);
        $winningAction = array_keys($actions)[0];

        $text = '';
        $random = rand(1, 2);
        if ($random < 2) {
            $text .= 'Ultimately ' . $this->getRandomFencerIdentifier($winningFencer) . ' was able to close out and win the bout. ';
        } else {
            $text .= 'In the end ' . $this->getRandomFencerIdentifier($winningFencer) . ' managed to win the bout. ';
        }

        if ($actions[$winningAction] > 1) {
            $text .= 'His '
                . $winningAction . 's proved too much for ' . $this->getRandomFencerIdentifier($losingFencer) . '. ';
        }
        $text .= 'We can learn a lot from careful analysis of bouts like this. If you thought this was helpful like and subscribe! ';

        return $text;
    }
}
