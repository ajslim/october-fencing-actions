<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class DownloadAudioForBouts extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:downloadaudioforbouts';

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

    private function makeBoutFolder($boutFolder)
    {
        if ($boutFolder !== null) {
            $this->createFolderIfNotExist($this->boutRootFolder);
            $this->createFolderIfNotExist($boutFolder);
        }
    }


    private function downloadAudio($bout)
    {
        $boutFolder = $this->getBoutFolder($bout);
        $folder = getcwd() . $boutFolder;

        $this->makeBoutFolder($boutFolder);

        // Delete previous videos
        if (file_exists("$folder/audio.m4a" )) {
            unlink("$folder/audio.m4a");
        }

        echo "Checking bout \n";
        echo $bout->video_url . "\n";

        $boutDetails = json_decode(exec( "youtube-dl -j \"$bout->video_url\""));

        if (isset($boutDetails->duration) !== true) {
            echo 'Bout has no duration' . "\n";
            return false;
        }

        if ($boutDetails->duration > 5400) {
            echo "bout more than 1:300 - too long \n";
            return false;
        }

        echo "Downloading bout \n";

        echo exec( "youtube-dl -f 140  \"$bout->video_url\" --output $folder/audio.m4a");
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
                $this->downloadAudio($bout);
            }
        } else {
            $bouts = Bout::whereNotNull('video_url')->get();
            $total = count($bouts);
            $count = 0;
            foreach($bouts as $bout) {
                echo ++$count . '/' . $total . "\n";
                if ($bout->video_url !== null && strlen($bout->video_url) > 11) {
                    $this->downloadAudio($bout);
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
