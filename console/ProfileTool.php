<?php namespace Ajslim\Fencingactions\Console;

use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class ProfileTool extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:profiletool';

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
    private $debug = false;
    private $noDownload = false;
    private $makeLightImagesOption = false;
    private $forceProfile = null;
    private $start = null;
    private $end = null;
    private $keepThumbs = false;

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


    private function getOverlayProfileWrapper($profileFolder)
    {
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
     * Saves cropped light images if the make light images flag is set. This is useful for converting
     * profiles to type 2
     *
     * @param bool    $isRed
     * @param bool    $isGreen
     * @param Imagick $image
     * @param integer $imageNumber
     */
    private function makeLightImages($isRed, $isGreen, Imagick $image, $imageNumber)
    {
        if ($this->makeLightImagesOption === true) {
            if ($isRed === true) {
                $redLightImage = clone $image;
                $redLightImage->cropImage(...$this->redLightCrop);
                $redLightImage->writeImage(getcwd() . $this->boutFolder . "/lights/red-$imageNumber.png");
            }

            if ($isGreen === true) {
                $greenLightImage = clone $image;
                $greenLightImage->cropImage(...$this->greenLightCrop);
                $greenLightImage->writeImage(getcwd() . $this->boutFolder . "/lights/green-$imageNumber.png");
            }
        }
    }


    /**
     * @return bool|mixed
     * @throws \ImagickException
     */
    private function findProfile()
    {
        $profile = $this->argument('profile');

        $profileFolder = getcwd()
            . "/plugins/ajslim/fencingactions/overlay-profiles/"
            . $profile;
        return $this->getOverlayProfileWrapper($profileFolder);
    }


    /**
     * Execute the console command.
     *
     * @return void
     * @throws \ImagickException
     */
    public function handle()
    {
        $profileWrapper = $this->findProfile();

        if ($profileWrapper === false) {
            echo "Profile not found\n";
            return;
        }

        $this->url = $this->argument('url');

        $parameters = [];
        parse_str( parse_url( $this->url, PHP_URL_QUERY ), $parameters );
        $this->videoId = $parameters['v'];

        $thumbNumber = $this->argument('thumb-number');

        $this->setProfileValues($profileWrapper);

        $this->boutFolder = $this->boutRootFolder . "/" . $this->videoId;

        $boutFolder = getcwd() . $this->boutFolder;
        $filename = $boutFolder . '/thumbs/thumb' . $thumbNumber . '.png';

        $image = new Imagick($filename);
        $image->resizeImage(
            $profileWrapper['profile']['imageDimensions'][0],
            $profileWrapper['profile']['imageDimensions'][1],
            Imagick::FILTER_POINT,
            0
        );

        $redLight = clone $image;
        $greenLight = clone $image;
        $overlay = clone $image;

        $redLight->cropImage(...$profileWrapper['profile']['redLightCrop']);
        $greenLight->cropImage(...$profileWrapper['profile']['greenLightCrop']);
        $overlay->cropImage(...$profileWrapper['profile']['overlayCrop']);

        $redLight->writeImage(getcwd() . $this->boutFolder . "/lights/red.png");
        $greenLight->writeImage(getcwd() . $this->boutFolder . "/lights/green.png");
        $overlay->writeImage(getcwd() . $this->boutFolder . "/lights/overlay.png");
    }

    /**
     * Get the console command arguments.
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['url', InputArgument::REQUIRED, 'The youtube url'],
            ['thumb-number', InputArgument::REQUIRED, 'The thumb number'],
            ['profile', InputArgument::REQUIRED, 'The profile'],
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [

        ];
    }
}
