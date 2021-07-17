<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreateAudioForActions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:createaudioforactions';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $boutRootFolder = "/storage/bout";

    private static function formatTime($t, $f=':') // t = seconds, f = separator
    {
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
    }


    /**
     * @param $folderString
     * @return string
     */
    private function createFolderIfNotExist($folderString)
    {
        $folder = getcwd() . $folderString;

        // make the bout directory if needed
        if (!file_exists($folder)) {
            echo "creating $folder\n";

            mkdir($folder);
        }
        return $folder;
    }

    private function getBoutFolder($bout)
    {
        if ($bout->video_url !== null && strlen($bout->video_url) > 11) {
            $urlLength = strlen($bout->video_url);
            $youtubeId = substr($bout->video_url, $urlLength - 11);
            return $this->boutRootFolder . "/" . $youtubeId;
        }
        return null;
    }

    private function getAudioFolder($bout)
    {
        if ($bout->video_url !== null && strlen($bout->video_url) > 11) {
            $urlLength = strlen($bout->video_url);
            $youtubeId = substr($bout->video_url, $urlLength - 11);
            return $this->boutRootFolder . "/" . $youtubeId . '/' . 'audio';
        }
        return null;
    }

    private function makeAudioFolder($audioFolder)
    {
        if ($audioFolder !== null) {
            $this->createFolderIfNotExist($audioFolder);
        }
    }


    private function createAudio($bout)
    {
        $boutFolder = $this->getBoutFolder($bout);
        $folder = getcwd() . $boutFolder;

        $audioFolder = $this->getAudioFolder($bout);
        $this->makeAudioFolder($audioFolder);

        // Delete previous videos
        if (file_exists("$folder/audio.m4a" ) !== true) {
            echo 'No Audio found (run DownloadAudioForBout';
            return true;
        }

        echo "Creating Audio from actions \n";

        $actions = $bout->actions;

        foreach($actions as $action) {
            $duration = 7;
            $startSeconds = $action->time;

            // The sample will be 1 frame too far
            $startTime = ($startSeconds - ($duration - 1));
            echo exec("ffmpeg -ss $startTime -i $folder/audio.m4a -t $duration -y -c copy $folder/audio/$startSeconds.m4a");
        }

        echo "\n";
        return true;
    }


    /**
     * Execute the console command.
     *
     * @return void
     * @throws \ImagickException
     */
    public function handle()
    {
        $boutId = $this->option('bout-id');
        if ($boutId !== null) {
            $bout = Bout::find($boutId);
            if ($bout->video_url !== null && strlen($bout->video_url) > 11) {
                $this->createAudio($bout);
            }
        } else {
            $bouts = Bout::whereNotNull('video_url')->get();
            $total = count($bouts);
            $count = 0;
            foreach($bouts as $bout) {
                echo ++$count . '/' . $total . "\n";
                if ($bout->video_url !== null && strlen($bout->video_url) > 11) {
                    $this->createAudio($bout);
                }
            }
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
            ['bout-id', null, InputOption::VALUE_OPTIONAL, 'The id of the bout', null],
        ];
    }
}
