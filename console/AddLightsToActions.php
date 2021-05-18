<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;


class AddLightsToActions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:addlightstoactions';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $boutRootFolder = "/storage/bout";
    private $currentBoutFolder;

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
    private $forceProfile = null;
    private $start = null;
    private $end = null;

    // Frame counts for analysis
    private $doubleLightCount = 0;
    private $singleGreenCount = 0;
    private $singleRedCount = 0;

    private static function formatTime($t, $f=':') // t = seconds, f = separator
    {
        return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
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
        if ($this->debug === true) {
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

            if ($this->debug === true) {
                echo "r:" . $this->redLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] . "\n";
            }

            return $this->redLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] < $this->redLightThreshold;
        }

        $redAmount = $this->checkRedAmount($image)[1];
        $isRed = $redAmount > $this->redLightThreshold;
        if ($this->debug === true) {
            echo "r:" . $redAmount . "\n";
        }
        return $isRed;
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

            if ($this->debug === true) {
                echo "g!:" . $this->greenLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] . "\n";
            }
            return $this->greenLightImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] < $this->greenLightThreshold;
        }

        $greenAmount = $this->checkGreenAmount($image)[1];
        $isGreen = $greenAmount > $this->greenLightThreshold;
        if ($this->debug === true) {
            echo "g:" . $greenAmount . "\n";
        }
        return $isGreen;
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
     * @return bool|mixed
     * @throws \ImagickException
     */
    private function findProfile()
    {
        if ($this->forceProfile !== null) {
            $profileFolder = getcwd()
                . "/plugins/ajslim/fencingactions/overlay-profiles/"
                . $this->forceProfile;
            return $this->getOverlayProfileWrapper($profileFolder);
        }

        $profileRootDirectory = getcwd(). "/plugins/ajslim/fencingactions/overlay-profiles/";
        $profileFolders = array_filter(glob($profileRootDirectory . '*'), 'is_dir');

        $overlayProfileWrappers = [];
        foreach ($profileFolders as $profileFolder)
        {
            $overlayProfileWrappers[] = $this->getOverlayProfileWrapper($profileFolder);
        }

        echo "Searching for matching profile\n";

        $profileBestMatchIndex = null;
        $profileBestMatch = 10000;
        $imageFolder = getcwd() . $this->currentBoutFolder;
        $images = array_filter(glob($imageFolder . '/lightthumbs/*'), 'is_file');
        foreach ($images as $index => $filename) {
            if ($this->start !== null && $index < $this->start) {
                continue;
            }

            if ($this->end !== null && $index > $this->end) {
                break;
            }

            // Check to see if the overlay matches any of the profiles
            foreach ($overlayProfileWrappers as $profileIndex => $profileWrapper) {
                if ($this->debug) {
                    echo $profileWrapper['profile']['name'] . "\n";
                }

                $image = new Imagick($filename);
                $image->resizeImage(
                    $profileWrapper['profile']['imageDimensions'][0],
                    $profileWrapper['profile']['imageDimensions'][1],
                    Imagick::FILTER_POINT,
                    0
                );

                $overlayMatch
                    = $this->checkOverlayAmount($image, $profileWrapper['profile'], $profileWrapper['overlay'])[1];

                if ($overlayMatch < $profileBestMatch) {
                    $profileBestMatch = $overlayMatch;
                    $profileBestMatchIndex = $profileIndex;
                }
            }
        }

        if ($profileBestMatchIndex === null) {
            return false;
        }

        // If the best match meets the profile minimum threshold the return
        $profileWrapper = $overlayProfileWrappers[$profileBestMatchIndex];
        if ($profileBestMatch < $profileWrapper['profile']['overlayThreshold']) {
            echo "Using profile " . $profileWrapper['profile']['name'] . "\n";
            return $profileWrapper;
        }

        // Return false if not found
        return false;
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
    private function handleImage($filename, $profileWrapper)
    {
        $image = new Imagick($filename);
        $image->resizeImage(
            $profileWrapper['profile']['imageDimensions'][0],
            $profileWrapper['profile']['imageDimensions'][1],
            Imagick::FILTER_POINT,
            0
        );

        // If the overlay is showing
        if ($this->checkIsOverlay($image, $profileWrapper['profile'], $profileWrapper['overlay']) === true) {
            $isRed = $this->checkIsRed($image);
            $isGreen = $this->checkIsGreen($image);

            $relativeFilename = substr($filename, 39);
            $action = Action::where('thumb_url', $relativeFilename)->first();

            if ($action === null) {
                return;
            }

            echo $action->id . ":";

            $isLight = $isRed || $isGreen;
            if ($isLight) {
                if ($isRed) {
                    echo " - Red";

                    if (!$isGreen) {
                        $this->singleRedCount += 1;
                    }

                    $action->left_coloured_light = true;
                }

                if ($isGreen) {
                    echo " - Green";

                    if (!$isRed) {
                        $this->singleGreenCount += 1;
                    }

                    $action->right_coloured_light = true;
                }

                if ($isGreen && $isRed) {
                    $this->doubleLightCount += 1;
                }

                $action->save();
                echo "\n";
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
        $boutQuery = Bout::whereNotNull('video_url');

        if ($this->option('bout_id') !== null) {
            $boutQuery->where('id', $this->option('bout_id'));
        }

        $bouts = $boutQuery->get();

        foreach ($bouts as $bout) {
            $parameters = [];
            parse_str( parse_url( $bout->video_url, PHP_URL_QUERY ), $parameters );
            echo $bout->video_url;

            if (isset($parameters['v']) === false) {
                echo "No Video Id \n";
                continue;
            }
            $this->videoId = $parameters['v'];

            $this->currentBoutFolder =$this->boutRootFolder . "/" . $this->videoId;

            $profileWrapper = $this->findProfile();

            if ($profileWrapper === false) {
                echo "Profile not found\n";
                continue;
            }

            $bout->overlay_profile = $profileWrapper['profile']['name'];
            $bout->save();

            $this->setProfileValues($profileWrapper);

            $boutFolder = getcwd() . $this->currentBoutFolder;
            $images = array_filter(glob($boutFolder . '/lightthumbs/*'), 'is_file');

            $this->doubleLightCount = 0;
            foreach ($images as $imageNumber => $filename) {
                $this->handleImage($filename, $profileWrapper);
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
            ['bout_id', null, InputOption::VALUE_OPTIONAL, 'The Bout id', null],
        ];
    }
}
