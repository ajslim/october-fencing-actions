<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;


class GuessCallOnAction extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:guesscallonaction';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';


    public function guessAction($action) {
        $fencerPositions = json_decode($action->fencer_movement);
        $bladeFrames = json_decode($action->blade_contacts);
        $lightFrameNumber = $action->light_frame_number;

        if ($fencerPositions === null || $bladeFrames === null || $lightFrameNumber >= count($fencerPositions)) {
            return null;
        }

        $offsetFrames = 4;
        if ($action->bout->light_frame_offset !== null) {
            $offsetFrames += $action->bout->light_frame_offset;
        }
        $lastFramesLength = 15;
        $startFrame = $lightFrameNumber - ($lastFramesLength + $offsetFrames);
        $endFrame =  $lightFrameNumber - $offsetFrames;

        $lastFrames = array_slice($fencerPositions, $startFrame, $endFrame - $startFrame);

        $lastAverageVelocities = [
            $lastFrames[$lastFramesLength - 1][0] - $lastFrames[0][0],
            $lastFrames[$lastFramesLength - 1][1] - $lastFrames[0][1],
        ];

        $fencerVelocities = [];
        for ($i = 1; $i < count($fencerPositions); $i += 1) {
            $fencerVelocities[$i-1] = [
                $fencerPositions[$i][0] - $fencerPositions[$i - 1][0],
                $fencerPositions[$i][1] - $fencerPositions[$i - 1][1]
            ];
        }

        $leftStartForwardFrame = null;
        $rightStartForwardFrame = null;
        $stopVelocity = 0.5;
        $lastLeft = null;
        $lastRight = null;



        for ($i = ($lightFrameNumber - $offsetFrames); $i >= 0; $i -= 1) {
            if ($leftStartForwardFrame === null && $fencerVelocities[$i][0] < $stopVelocity) {
                $leftStartForwardFrame = $i;
            }
            if ($rightStartForwardFrame === null && $fencerVelocities[$i][1] > -$stopVelocity) {
                $rightStartForwardFrame = $i;
            }
        }

        $count = 0;
        $leftSum = 0;
        $rightSum = 0;

        // Average on every nth frame
        $sampleRate = 5;
        $stopThreshold = 0.5;
        $slowThreshold = 10;

        $directionOutput = '';
        foreach ($fencerVelocities as $fencerVelocity) {
            $leftSum += $fencerVelocity[0];
            $rightSum += $fencerVelocity[1];
            if ($count % $sampleRate === 0) {
                if (($leftSum/$sampleRate) > $slowThreshold) {
                    $directionOutput .= '>:';
                } elseif (($leftSum/$sampleRate) < -$slowThreshold) {
                    $directionOutput .= '<:';
                } elseif (($leftSum/$sampleRate) > $stopThreshold) {
                    $directionOutput .= '}:';
                } elseif (($leftSum/$sampleRate) < -$stopThreshold) {
                    $directionOutput .= '{:';
                } else {
                    $directionOutput .= '|:';
                }

                if (($rightSum/$sampleRate) > $slowThreshold) {
                    $directionOutput .= '>,';
                } elseif (($rightSum/$sampleRate) < -$slowThreshold) {
                    $directionOutput .= '<,';
                } elseif (($rightSum/$sampleRate) > $stopThreshold) {
                    $directionOutput .= '},';
                } elseif (($rightSum/$sampleRate) < -$stopThreshold) {
                    $directionOutput .= '{,';
                } else {
                    $directionOutput .= '|,';
                }

                $leftSum = 0;
                $rightSum = 0;
            }

            $count += 1;
        }

        echo "Direction:" . $directionOutput . "\n";

        $action->direction_output = $directionOutput;

        $beginsFirst = 0;
        if ($leftStartForwardFrame < $rightStartForwardFrame) {
            $beginsFirst = 1;
        } else if ($leftStartForwardFrame > $rightStartForwardFrame) {
            $beginsFirst = 2;
        }
        echo "Left begins: " . $leftStartForwardFrame . " - " . "Right begins: " . $rightStartForwardFrame . "\n";

        $initiator = 0;
        $velocityThreshhold = 10;
        if ($lastAverageVelocities[0] > (-$lastAverageVelocities[1])) {
            if ($lastAverageVelocities[0] > $velocityThreshhold) {
                $initiator = 1;
                echo 'Initiator: Left' . "\n";
            }
        } else {
            if (-$lastAverageVelocities[1] > $velocityThreshhold) {
                $initiator = 2;
                echo 'Initiator: Right' . "\n";
            }
        }


        echo 'Light on frame: '  . $lightFrameNumber . "\n";
        echo 'Average Velocities: ' . $lastAverageVelocities[0] .  ' : ' . -$lastAverageVelocities[1] . "\n";

        $bladeContact = false;
        foreach($bladeFrames as $bladeFrame) {
            if ($bladeFrame[0] >= $startFrame && $bladeFrame[0] < $endFrame) {
                $bladeContact = true;
                break;
            }
        }

       $leftLight = (int)($action->left_coloured_light || $action->left_off_target_light);
       $rightLight = (int)($action->right_coloured_light || $action->right_off_target_light);

        $lightOutput = '';
        if ($leftLight === 1) {
            $lightOutput .= 'Left - ';
        } else {
            $lightOutput .= '____ - ';
        }
        if ($rightLight === 1) {
            $lightOutput .= 'Right';
        } else {
            $lightOutput .= '_____';
        }

        echo $lightOutput . "$\n";


        $likelyAttacker = $beginsFirst;
        if ($likelyAttacker === 0) {
            $likelyAttacker = $initiator;
        }

        $guessedAction = 'abstain';
        $guessedCall = null;
        if ($bladeContact === false) {
            if ($leftLight === 1
                && $likelyAttacker === 1
            ) {
                $guessedAction = 'Attack for the left';
                $guessedCall = '1:1';
            } else if ($rightLight === 1
                && $likelyAttacker === 2
            ) {
                $guessedAction = 'Attack for the right';
                $guessedCall = '2:1';
            } else if ($leftLight !== 1
                && $rightLight === 1
                && $likelyAttacker === 1
            ) {
    //            $guessedAction = 'Counter Attack for the right';
    //            $guessedCall = '2:2';
            } else if ($leftLight === 1
                && $rightLight !== 1
                && $likelyAttacker === 2
            ) {
    //            $guessedAction = 'Counter Attack for the left';
    //            $guessedCall = '1:2';
            }
        } else {
    //        if ($leftLight === 1
    //            && $rightLight !== 1
    //            && $likelyAttacker === 1
    //        ) {
    //            $guessedAction = 'Attack for the left';
    //            $guessedCall = '1:1';
    //        } else if ($leftLight !== 1
    //            && $rightLight === 1
    //            && $likelyAttacker === 2
    //        ) {
    //            $guessedAction = 'Attack for the right';
    //            $guessedCall = '2:1';
    //        } else if ($leftLight !== 1
    //            && $rightLight === 1
    //            && $likelyAttacker === 1
    //        ) {
    //            $guessedAction = 'Riposte for the right';
    //            $guessedCall = '2:3';
    //        } else if ($leftLight === 1
    //            && $rightLight !== 1
    //            && $likelyAttacker === 2
    //        ) {
    //            $guessedAction = 'Riposte for the left';
    //            $guessedCall = '1:3';
    //        }
        }


         echo "Computer: " . $guessedAction . "\n";

        return $guessedCall;
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $actionId = $this->option('action-id');
        $maxId = $this->option('max-id');
        $minId = $this->option('min-id');

        if ($actionId !== null) {
            $actions = Action::where('id', $actionId)->get();
        } else {
            $actions = Action::where('id', '>', $minId)
                ->where('id', '<', $maxId)
                ->get();
        }

        $count = $actions->count();
        $current = 0;

        $correctCount = 0;
        $incorrectCount = 0;
        $bouts = [];
        $runningAverages = [];
        $runningAverages[0] = .3;
        foreach ($actions as $action) {
            $current += 1;
            echo $current . '/' . $count . "\n";
            echo "-----------------------------\n";
            echo $action->id . "\n";
            echo "-----------------------------\n";

            $guessedCall = $this->guessAction($action);

            // Will set as null if no guess
            $action->computer_guess_call_cache = $guessedCall;
            $action->save();

            if (!isset($bouts[$action->bout_id])) {
                $bouts[$action->bout_id] = [
                    'correct' => 0,
                    'incorrect' => 0
                ];
            }
            if ($guessedCall !== null
                && $action->top_call_cache !== null
                && $action->top_call_cache !== ''
            ) {

                if ($guessedCall === $action->top_call_cache) {
                    echo "Guessed Correct \n";

                    $bouts[$action->bout_id]['correct'] += 1;
                    $correctCount += 1;
                } else {
                    echo "Guessed Incorrect \n";
                    $incorrectCount += 1;
                    $bouts[$action->bout_id]['incorrect'] += 1;
                }
            }


            if ($incorrectCount + $correctCount !== 0) {
                $runningAverages[$current] = ($runningAverages[$current - 1] + ($correctCount / ($incorrectCount + $correctCount))) / 2;
            } else {
                $runningAverages[$current] = $runningAverages[$current - 1];
            }

        }

        $boutAverages = [];
        foreach($bouts as $index => $bout) {
            if ($bout['incorrect'] + $bout['correct'] !== 0) {
                $boutAverages[$index] = $bout['correct'] / ($bout['incorrect'] + $bout['correct']);
            }
        }

        asort($boutAverages);
        $count = 0;
        foreach ($boutAverages as $index => $boutAverage) {
            $count++;
            echo $index . ':' .$boutAverage . "\n";
            if ($count >= 10) {
                break;
            }
        }

        echo '------------------------' . "\n";

        foreach ($runningAverages as $average) {
            $count++;
            if ($count % 25 === 0) {
                echo $count . ':' . round($average, 4) . "\n";
            }
        }


        if ($incorrectCount + $correctCount !== 0) {
            echo "Guessed:" . $correctCount . '/' . ($incorrectCount + $correctCount) . "\n";
            echo "Average:" . ($correctCount / ($incorrectCount + $correctCount)) . "\n";


        }
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['action-id', null, InputOption::VALUE_OPTIONAL, 'The Action id', null],
            ['max-id', null, InputOption::VALUE_OPTIONAL, 'The Action id to stop at', 100000],
            ['min-id', null, InputOption::VALUE_OPTIONAL, 'The Action id to start at', 0],
        ];
    }
}
