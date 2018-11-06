<?php namespace Ajslim\Fencingactions\Console;

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

    private $sampleRate = 1;
    private $boutRootFolder = "/storage/bout";
    private $boutFolder;
    private $videoId;
    private $url;

    private $redLightCrop;
    private $greenLightCrop;
    private $redLightThreshold;
    private $greenLightThreshold;

    private $profileType = 1;

    /* @var Imagick $redLightImage */
    private $redLightImage;

    /* @var Imagick $greenLightImage */
    private $greenLightImage;

    // Options
    private $debugThresholds = false;
    private $noDownload = false;
    private $forceProfile = null;
    private $start = null;
    private $end = null;

    // Frame counts for analysis
    private $doubleLightCount = 0;
    private $singleGreenCount = 0;
    private $singleRedCount = 0;
    private $lightCount = 0;
    private $lastLightFrame = 0;

    private static function formatTime($t, $f=':') // t = seconds, f = separator
    {
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
    }


    /**
     * @param $folderString
     * @return string
     */
    private function createFolderIfNotExist($folderString): string
    {
        $folder = getcwd() . $folderString;

        // make the bout directory if needed
        if (!file_exists($folder)) {
            mkdir($folder);
        }
        return $folder;
    }


    private function makeBoutFolder()
    {
        $this->boutFolder = $this->boutRootFolder . "/" . $this->videoId;

        $this->createFolderIfNotExist($this->boutRootFolder);
        $this->createFolderIfNotExist($this->boutFolder);
    }


    private function makeLightThumbsDirectory()
    {
        $folder = getcwd() . $this->boutFolder;

        // make the thumbs directory if needed
        if (!file_exists($folder . '/lightthumbs')) {
            mkdir($folder . '/lightthumbs');
        }

        // Delete all old thumbs
        $files = glob($folder . '/lightthumbs/*'); // get all file names
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

        exec( "youtube-dl -f 134  \"$this->url\" --output $folder/video.mp4");
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

    /**
     * @param Imagick $image
     * @param $overlayProfile
     * @param Imagick $overlayImage
     * @return array
     * @throws \ImagickException
     */
    private function checkOverlayAmount(Imagick $image, $overlayProfile, Imagick $overlayImage)
    {
        $overlayImageCheck = clone $image;
        $overlayImageCheck->cropImage(...$overlayProfile['overlayCrop']);
        return $overlayImage->compareImages($overlayImageCheck, Imagick::METRIC_MEANSQUAREERROR);
    }


    /**
     * @param Imagick $image
     * @param $overlayProfile
     * @param $overlayImage
     * @return bool
     * @throws \ImagickException
     */
    private function checkIsOverlay(Imagick $image, $overlayProfile, $overlayImage)
    {
        if ($this->debugThresholds === true) {
            echo "o:" . $this->checkOverlayAmount($image, $overlayProfile, $overlayImage)[1] . "\n";
        }

        $result = $this->checkOverlayAmount($image, $overlayProfile, $overlayImage);
        return $result[1] < $overlayProfile['overlayThreshold'];
    }


    /**
     * @param Imagick $image
     * @return array
     * @throws \ImagickException
     */
    private function checkRedAmount(Imagick $image)
    {
        $redLightImageCheck = clone $image;
        $redLightImageCheck->cropImage(...$this->redLightCrop);

        $redLightImageCheck2 = clone $redLightImageCheck;
        $redLightImageCheck2->separateImageChannel(Imagick::CHANNEL_RED);

        return $redLightImageCheck->compareImages($redLightImageCheck2, Imagick::METRIC_MEANSQUAREERROR);
    }


    /**
     * @param Imagick $image
     * @return bool
     * @throws \ImagickException
     */
    private function checkIsRed(Imagick $image)
    {
        if ($this->profileType === 2) {
            $imageLightSection = clone $image;
            $imageLightSection->cropImage(...$this->redLightCrop);

            if ($this->debugThresholds === true) {
                echo "r:" . $this->redLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] . "\n";
            }

            return $this->redLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] < $this->redLightThreshold;
        }

        if ($this->debugThresholds === true) {
            echo "r:" . $this->checkRedAmount($image)[1] . "\n";
        }

        return $this->checkRedAmount($image)[1] > $this->redLightThreshold;
    }


    /**
     * @param Imagick $image
     * @return array
     * @throws \ImagickException
     */
    private function checkGreenAmount(Imagick $image)
    {
        $greenLightImageCheck = clone $image;
        $greenLightImageCheck->cropImage(...$this->greenLightCrop);

        $greenLightImageCheck2 = clone $greenLightImageCheck;
        $greenLightImageCheck2->separateImageChannel(Imagick::CHANNEL_GREEN);

        return $greenLightImageCheck->compareImages($greenLightImageCheck2, Imagick::METRIC_MEANSQUAREERROR);
    }


    /**
     * @param Imagick $image
     * @return bool
     * @throws \ImagickException
     */
    private function checkIsGreen(Imagick $image)
    {
        if ($this->profileType === 2) {
            $imageLightSection = clone $image;
            $imageLightSection->cropImage(...$this->greenLightCrop);

            if ($this->debugThresholds === true) {
                echo "g!:" . $this->greenLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] . "\n";
            }
            return $this->greenLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] < $this->greenLightThreshold;
        }

        if ($this->debugThresholds === true) {
            echo "g:" . $this->checkGreenAmount($image)[1] . "\n";
        }
        return $this->checkGreenAmount($image)[1] > $this->greenLightThreshold;
    }


    /**
     * @return bool|mixed
     * @throws \ImagickException
     */
    private function findProfile()
    {
        if ($this->forceProfile !== null) {

            $profileFolder = getcwd()
                . "/plugins/ajslim/fencingactions/overlay-profiles/"
                . $this->forceProfile;
            $json = file_get_contents(
                $profileFolder
                . "/profile.json"
            );
            $profile = json_decode($json, true);

            $overlayProfileWrapper = [
                'overlay' => new Imagick( $profileFolder . "/overlay.png"),
                'profile' => $profile
            ];


            if (isset($overlayProfileWrapper['profile']['type']) === true
                && $overlayProfileWrapper['profile']['type'] === 2
            ) {
                $overlayProfileWrapper['redLightImage'] = new Imagick($profileFolder . "/red.png");
                $overlayProfileWrapper['greenLightImage'] = new Imagick($profileFolder . "/green.png");
            }
            return $overlayProfileWrapper;
        }

        $profileRootDirectory = getcwd(). "/plugins/ajslim/fencingactions/overlay-profiles/";
        $profileFolders = array_filter(glob($profileRootDirectory . '*'), 'is_dir');

        $overlayProfileWrappers = [];

        foreach ($profileFolders as $profileFolder)
        {
            $json = file_get_contents( $profileFolder . "/profile.json");
            $profile = json_decode($json, true);


            $overlayProfileWrapper = [
                'overlay' => new Imagick( $profileFolder . "/overlay.png"),
                'profile' => $profile
            ];


            if (isset($overlayProfileWrapper['profile']['type']) === true
                && $overlayProfileWrapper['profile']['type'] === 2
            ) {
                $overlayProfileWrapper['redLightImage'] = new Imagick($profileFolder . "/red.png");
                $overlayProfileWrapper['greenLightImage'] = new Imagick($profileFolder . "/green.png");
            }

            // Only add matching profiles, or all if force profile is null
            $overlayProfileWrappers[] = $overlayProfileWrapper;
        }

        $imageFolder = getcwd() . $this->boutFolder;

        $images = array_filter(glob($imageFolder . '/thumbs/*'), 'is_file');

        foreach ($images as $index => $filename) {


            if ($this->start !== null
                && $index < $this->start
            ) {
                continue;
            }

            if ($this->end !== null
                && $index > $this->end
            ) {
                break;
            }

            // Ignore non thumbnail files
            if (strpos($filename, 'thumb') === false) {
                echo $filename . "\n";
                continue;
            }

            // Check every 10 images for speed sake
            if ($index % 10 !== 0) {
                continue;
            }

            if ($this->debugThresholds) {
                echo $index . "\n";
            }

            // Check to see if the overlay matches any of the profiles
            foreach ($overlayProfileWrappers as $profileWrapper) {
                if ($this->debugThresholds) {
                    echo $profileWrapper['profile']['name'] . "\n";
                }

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
     * Assigns options to local variables
     */
    private function handleOptionsAndArguments()
    {
        $this->url = $this->argument('url');

        $parameters = [];
        parse_str( parse_url( $this->url, PHP_URL_QUERY ), $parameters );
        $this->videoId = $parameters['v'];

        $this->forceProfile = $this->option('profile');
        $this->start = $this->option('start');
        $this->end = $this->option('end');

        if ($this->option('no-download') !== null) {
            $this->noDownload = true;
        }

        if ($this->option('debug-thresholds') !== null) {
            $this->debugThresholds = true;
        }
    }


    /**
     * Sets the red and green crops and profiles
     *
     * @param $profileWrapper
     */
    private function setProfileValues($profileWrapper)
    {
        $this->redLightCrop = $profileWrapper['profile']['redLightCrop'];
        $this->greenLightCrop = $profileWrapper['profile']['greenLightCrop'];
        $this->redLightThreshold = $profileWrapper['profile']['redLightThreshold'];
        $this->greenLightThreshold = $profileWrapper['profile']['greenLightThreshold'];

        if (isset($profileWrapper['profile']['type']) === true) {
            $this->profileType = (int)$profileWrapper['profile']['type'];
        }

        if ($this->profileType === 2) {
            $this->redLightImage = $profileWrapper['redLightImage'];
            $this->greenLightImage = $profileWrapper['greenLightImage'];
        }

    }



    /**
     * Handles a single image
     *
     * @param $filename
     * @param $profileWrapper
     * @param $imageNumber
     * @throws \ImagickException
     */
    private function handleImage($filename, $profileWrapper, $imageNumber): void
    {
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
                if ($imageNumber > $this->lastLightFrame + 1) {
                    $this->lightCount += 1;

                    echo $this->lightCount . ': ';
                    $seconds = ($imageNumber + 1) / $this->sampleRate;
                    echo self::formatTime($seconds) . " - ";
                    echo $imageNumber + 1;  // ImageNumber is 0 indexed

                    if ($isRed) {
                        echo " - Red";

                        if (!$isGreen) {
                            $this->singleRedCount += 1;
                        }
                    }

                    if ($isGreen) {
                        echo " - Green";

                        if (!$isRed) {
                            $this->singleGreenCount += 1;
                        }
                    }

                    if ($isGreen && $isRed) {
                        $this->doubleLightCount += 1;
                    }

                    if ($this->debugThresholds === false) {
                        $this->makeClip($seconds);
                    }

                    $image->writeImage(getcwd() . $this->boutFolder . "/lightthumbs/$imageNumber.png");


                    echo "\n";
                }

                $this->lastLightFrame = $imageNumber;
            }
        }
    }


    /**
     * Execute the console command.
     *
     * @return void
     * @throws \ImagickException
     */
    public function handle()
    {
        $this->handleOptionsAndArguments();

        $this->makeBoutFolder();
        $this->makeLightThumbsDirectory();

        if ($this->noDownload === false) {
            $this->downloadVideo();
            $this->makeFrameImages();
            $this->deleteClips();
        }
        $profileWrapper = $this->findProfile();

        if ($profileWrapper === false) {
            echo "Profile not found\n";
            return;
        }

        $this->setProfileValues($profileWrapper);

        $boutFolder = getcwd() . $this->boutFolder;
        $images = array_filter(glob($boutFolder . '/thumbs/*'), 'is_file');

        $this->doubleLightCount = 0;
        foreach ($images as $imageNumber => $filename) {
            // If before start, continue
            if ($this->start !== null && $imageNumber < $this->start) {
                continue;
            }

            // If after end, break
            if ($this->end !== null && $imageNumber > $this->end) {
                break;
            }

            // Ignore non thumbnail files
            if (strpos($filename, 'thumb') === false) {
                continue;
            }

            $this->handleImage($filename, $profileWrapper, $imageNumber);
        }

        if ($this->debugThresholds === true) {
            echo "Ignoring off targets\n";
            echo "Red Light Count: $this->singleRedCount\n";
            echo "Green Light Count: $this->singleGreenCount\n";
            echo "BOth: $this->doubleLightCount\n";
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
        return [
            ['debug-thresholds', null, InputOption::VALUE_OPTIONAL, 'Debug mode', null],
            ['no-download', null, InputOption::VALUE_OPTIONAL, 'No download', null],
            ['profile', null, InputOption::VALUE_OPTIONAL, 'Force a particular profile', null],
            ['start', null, InputOption::VALUE_OPTIONAL, 'Start Time in seconds', null],
            ['end', null, InputOption::VALUE_OPTIONAL, 'End Time in seconds', null],
        ];
    }
}
