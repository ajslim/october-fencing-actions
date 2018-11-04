<?php namespace Ajslim\Fencingactions\Console;

use DirectoryIterator;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AnalyzeBout extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:analyzebout';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $boutFolder = "/storage/temp/bout";

    private $redLightCrop;
    private $greenLightCrop;

    private $redLightThreshold;
    private $greenLightThreshold;

    private $debugThresholds = false;

    private $sampleRate = 1;

    private static function formatTime($t, $f=':') // t = seconds, f = separator
    {
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
    }

    private function makeLightThumbsDirectory()
    {
        $folder = getcwd() . $this->boutFolder;

        // make the thumbs directory if needed
        if (!file_exists($folder . '/likghtthumbs')) {
            mkdir($folder . '/likghtthumbs');
        }

        // Delete all old thumbs
        $files = glob($folder . '/likghtthumbs/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }
    }

    private function downloadVideo()
    {
        $folder = getcwd() . $this->boutFolder;

        // Make the bout folder if needed
        if (!file_exists($folder )) {
            mkdir($folder);
        }

        // Delete previous videos
        if (file_exists("$folder/video.mp4" )) {
            unlink("$folder/video.mp4");
        }

        echo "Downloading bout \n";

        exec( "youtube-dl -f 134  $this->url --output $folder/video.mp4");
    }

    private function deleteClips()
    {
        $folder = getcwd() . $this->boutFolder;
        // Delete all old clips

        $files = glob($folder . '/clips/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }
    }

    private function makeFrameImages()
    {
        $folder = getcwd() . $this->boutFolder;

        // make the thumbs directory if needed
        if (!file_exists($folder . '/thumbs')) {
            mkdir($folder . '/thumbs');
        }

        // Delete all old thumbs
        $files = glob($folder . '/thumbs/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }

        // make new thumbs
        exec( "ffmpeg -i $folder/video.mp4 -vf fps=$this->sampleRate $folder/thumbs/thumb%04d.png");
    }


    private function makeClip($startSeconds, $duration = 7)
    {
        $folder = getcwd() . $this->boutFolder;

        // make the clips directory if needed
        if (!file_exists($folder . '/clips')) {
            mkdir($folder . '/clips');
        }

        // The sample will be 1 frame too far
        $startTime = ($startSeconds - ($duration - 1));

        exec("ffmpeg -ss $startTime -i $folder/video.mp4 -t $duration -y -c copy $folder/clips/$startSeconds.mp4");
    }

    private function checkOverlayAmount(Imagick $image, $overlayProfile, Imagick $overlayImage)
    {
        $overlayImageCheck = clone $image;
        $overlayImageCheck->cropImage(...$overlayProfile['overlayCrop']);
        return $overlayImage->compareImages($overlayImageCheck, Imagick::METRIC_MEANSQUAREERROR);
    }


    private function checkIsOverlay(Imagick $image, $overlayProfile, $overlayImage)
    {
        if ($this->debugThresholds === true) {
            echo "o:" . $this->checkOverlayAmount($image, $overlayProfile, $overlayImage)[1] . "\n";
        }

        $result = $this->checkOverlayAmount($image, $overlayProfile, $overlayImage);
        return $result[1] < $overlayProfile['overlayThreshold'];
    }


    private function checkRedAmount(Imagick $image)
    {
        $redLightImageCheck = clone $image;
        $redLightImageCheck->cropImage(...$this->redLightCrop);

        $redLightImageCheck2 = clone $redLightImageCheck;
        $redLightImageCheck2->separateImageChannel(Imagick::CHANNEL_RED);

        return $redLightImageCheck->compareImages($redLightImageCheck2, Imagick::METRIC_MEANSQUAREERROR);
    }


    private function checkIsRed(Imagick $image)
    {
        if ($this->debugThresholds === true) {
            echo "r:" . $this->checkRedAmount($image)[1] . "\n";
        }
        return $this->checkRedAmount($image)[1] > $this->redLightThreshold;
    }


    private function checkGreenAmount(Imagick $image)
    {
        $greenLightImageCheck = clone $image;
        $greenLightImageCheck->cropImage(...$this->greenLightCrop);

        $greenLightImageCheck2 = clone $greenLightImageCheck;
        $greenLightImageCheck2->separateImageChannel(Imagick::CHANNEL_GREEN);

        return $greenLightImageCheck->compareImages($greenLightImageCheck2, Imagick::METRIC_MEANSQUAREERROR);
    }


    private function checkIsGreen(Imagick $image)
    {
        if ($this->debugThresholds === true) {
            echo "g:" . $this->checkGreenAmount($image)[1] . "\n";
        }
        return $this->checkGreenAmount($image)[1] > $this->greenLightThreshold;
    }


    private function findProfile()
    {
        $profileRootDirectory = getcwd(). "/plugins/ajslim/fencingactions/overlay-profiles/";
        $profileFolders = array_filter(glob($profileRootDirectory . '*'), 'is_dir');

        $overlayProfileWrappers = [];

        foreach ($profileFolders as $profileFolder)
        {
            $json = file_get_contents( $profileFolder . "/profile.json");
            $profileWrapper = json_decode($json, true);

            $overlayProfileWrappers[] = [
                'overlay' => new Imagick( $profileFolder . "/overlay.png"),
                'profile' => $profileWrapper
            ];
        }

        $imageFolder = getcwd() . $this->boutFolder;

        $images = array_filter(glob($imageFolder . '/thumbs/*'), 'is_file');

        foreach ($images as $index => $filename) {

            // Ignore non thumbnail files
            if (strpos($filename, 'thumb') === false) {
                echo $filename . "\n";
                continue;
            }

            // Check every 10 images for speed sake
            if ($index % 10 !== 0) {
                continue;
            }


            // Check to see if the overlay matches any of the profiles
            foreach ($overlayProfileWrappers as $profileWrapper) {
                $image = new Imagick($filename);
                $image->resizeImage(
                    $profileWrapper['profile']['imageDimensions'][0],
                    $profileWrapper['profile']['imageDimensions'][1],
                    Imagick::FILTER_POINT,
                    0
                );

                // If the overlay  is showing
                if ($this->checkIsOverlay($image, $profileWrapper['profile'], $profileWrapper['overlay']) === true) {
                    echo "found matching profile " . $profileWrapper['profile']['name'] . "\n";
                    return $profileWrapper;
                }
            }
        }

        // Return false if not found
        return false;
    }

    /**
     * Execute the console command.
     * @return void
     */
    public function handle()
    {
        $this->url = $this->argument('url');

        if ($this->debugThresholds === false) {
            $this->downloadVideo();
            $this->makeFrameImages();
            $this->deleteClips();
        }
        $profileWrapper = $this->findProfile();

        if ($profileWrapper === false) {
            echo "Profile not found\n";
            return;
        }

        if ($this->debugThresholds === true) {
            $this->makeLightThumbsDirectory();
        }

        $this->redLightCrop = $profileWrapper['profile']['redLightCrop'];
        $this->greenLightCrop = $profileWrapper['profile']['greenLightCrop'];
        $this->redLightThreshold = $profileWrapper['profile']['redLightThreshold'];
        $this->greenLightThreshold = $profileWrapper['profile']['greenLightThreshold'];


        $boutFolder = getcwd() . $this->boutFolder;
        $images = array_filter(glob($boutFolder . '/thumbs/*'), 'is_file');

        $lastLightFrame = 0;
        $lightCount = 0;
        foreach ($images as $imageNumber => $filename) {

            // Ignore non thumbnail files
            if (strpos($filename, 'thumb') === false) {
                continue;
            }

            $image = new Imagick($filename);
            $image->resizeImage(
                $profileWrapper['profile']['imageDimensions'][0],
                $profileWrapper['profile']['imageDimensions'][1],
                Imagick::FILTER_POINT,
                0
            );

            if ($this->debugThresholds === true) {
                echo $imageNumber . "\n";
            }

            // If the overlay is showing
            if ($this->checkIsOverlay($image, $profileWrapper['profile'], $profileWrapper['overlay']) === true) {
                $isRed = $this->checkIsRed($image);
                $isGreen = $this->checkIsGreen($image);

                $isLight = $isRed || $isGreen;
                if ($isLight) {
                    if ($imageNumber > $lastLightFrame + 1) {
                        $lightCount += 1;

                        echo $lightCount . ': ';
                        $seconds = ($imageNumber + 1) / $this->sampleRate;
                        echo self::formatTime($seconds) . " - ";
                        echo $imageNumber + 1;  // ImageNumber is 0 indexed

                        if ($isRed) {
                            echo " - Red";
                        }

                        if ($isGreen) {
                            echo " - Green";
                        }

                        if ($this->debugThresholds === false) {
                            $this->makeClip($seconds);
                        } else {
                            $image->writeImage(getcwd() . $this->boutFolder . "/lightthumbs/$imageNumber.png");
                        }

                        echo "\n";
                    }

                    $lastLightFrame = $imageNumber;
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
        return [
            ['url', InputArgument::REQUIRED, 'The youtube url'],
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
