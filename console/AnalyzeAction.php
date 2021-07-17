<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AnalyzeAction extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:analyzeaction';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $sampleRate = 25;
    private $sampleStart = 4;
    private $boutRootFolder = "/storage/bout";
    private $folder = '/var/tmp/frames';
    private $actionFolder;
    private $action;
    private $videoId;
    private $url;

    private $redLightCrop;
    private $greenLightCrop;
    private $leftOffCrop;
    private $rightOffCrop;
    private $redLightThreshold;
    private $greenLightThreshold;
    private $offTargetThreshold;

    private $profileType = 1;

    /* @var Imagick $redLightImage */
    private $redLightImage;

    /* @var Imagick $greenLightImage */
    private $greenLightImage;

    /* @var Imagick $greenLightImage */
    private $leftOffImage;

    /* @var Imagick $greenLightImage */
    private $rightOffImage;

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

    private function clearFramesDirectory()
    {
        // make the thumbs directory if needed
        if (!file_exists($this->folder)) {
            mkdir($this->folder);
        }

        // Delete all old thumbs
        $files = glob($this->folder. '/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }
    }

    private function makeFrameImages()
    {
        $folder = $this->folder;

        // Delete all old thumbs
        $files = glob($folder . '/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }

        $videoUri = getcwd() . $this->action->video_url;

        // make new thumbs
        exec( "ffmpeg -ss $this->sampleStart -i $videoUri -vf \"fps=$this->sampleRate\" $folder/thumb%04d.png");
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

    /**
     * @param Imagick $image
     * @return bool
     * @throws \ImagickException
     */
    private function checkIsLeftOff(Imagick $image)
    {
        if ($this->profileType === 2 && $this->leftOffImage !== null) {
            $imageLightSection = clone $image;
            $imageLightSection->cropImage(...$this->leftOffCrop);

            if ($this->debug === true) {
                echo "left off:" . $this->leftOffImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] . '<' . $this->offTargetThreshold . "\n";
            }

            return $this->leftOffImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] < $this->offTargetThreshold;
        }

        return false;
    }

    /**
     * @param Imagick $image
     * @return bool
     * @throws \ImagickException
     */
    private function checkIsRightOff(Imagick $image)
    {
        if ($this->profileType === 2 && $this->rightOffImage !== null) {
            $imageLightSection = clone $image;
            $imageLightSection->cropImage(...$this->rightOffCrop);

            if ($this->debug === true) {
                echo "right off:" . $this->rightOffImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] . '<' . $this->offTargetThreshold . "\n";
            }
            return $this->rightOffImage->compareImages($imageLightSection, Imagick::METRIC_MEANSQUAREERROR)[1] < $this->offTargetThreshold;
        }

        return false;
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

            if (file_exists($profileFolder . "/leftoff.png") === true) {
                $overlayProfileWrapper['leftOffImage'] = new Imagick($profileFolder . "/leftoff.png");
                $overlayProfileWrapper['rightOffImage'] = new Imagick($profileFolder . "/rightoff.png");
            }
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

        $profileBestMatch = 10000;
        $imageFolder = $this->folder;
        $images = array_filter(glob($imageFolder . '/*'), 'is_file');
        foreach ($images as $index => $filename) {
            if ($this->start !== null && $index < $this->start) {
                continue;
            }

            if ($this->end !== null && $index > $this->end) {
                break;
            }

            // Ignore non thumbnail files
            if (strpos($filename, 'thumb') === false) {
                echo $filename . "\n";
                continue;
            }

            // Check 5% images for speed sake
            if ($index % 20 !== 0) {
                continue;
            }

            if ($this->debug) {
                echo $index . "\n";
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
     * Assigns options to local variables
     */
    private function handleOptionsAndArguments()
    {
        $this->forceProfile = $this->option('profile');
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

            if (isset($profileWrapper['leftOffImage'])) {
                $this->leftOffImage = $profileWrapper['leftOffImage'];
                $this->rightOffImage = $profileWrapper['rightOffImage'];

                $this->leftOffCrop = $profileWrapper['profile']['leftOffCrop'];
                $this->rightOffCrop = $profileWrapper['profile']['rightOffCrop'];
                $this->offTargetThreshold = $profileWrapper['profile']['offTargetThreshold'];
            }
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
                $redLightImage->writeImage(getcwd() . $this->actionFolder . "/lights/red-$imageNumber.png");
            }

            if ($isGreen === true) {
                $greenLightImage = clone $image;
                $greenLightImage->cropImage(...$this->greenLightCrop);
                $greenLightImage->writeImage(getcwd() . $this->actionFolder . "/lights/green-$imageNumber.png");
            }
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
    private function makeOffTargetImage(Imagick $image, $imageNumber)
    {
        $redLightImage = clone $image;
        $redLightImage->cropImage(...$this->rightOffCrop);
        $redLightImage->writeImage(getcwd() . "leftOff-$imageNumber.png");
    }


    /**
     * Handles a single image
     *
     * @param $filename
     * @param $profileWrapper
     * @param $imageNumber
     * @throws \ImagickException
     */
    private function isLight($filename, $profileWrapper, $imageNumber)
    {
        $image = new Imagick($filename);
        $image->resizeImage(
            $profileWrapper['profile']['imageDimensions'][0],
            $profileWrapper['profile']['imageDimensions'][1],
            Imagick::FILTER_POINT,
            0
        );

        if ($this->debug === true) {
            echo $imageNumber . "\n";
        }

        // If the overlay is showing
        if ($this->checkIsOverlay($image, $profileWrapper['profile'], $profileWrapper['overlay']) === true) {
            $isRed = $this->checkIsRed($image);
            $isGreen = $this->checkIsGreen($image);

            $isLight = $isRed || $isGreen;
            if ($isLight) {
                $isLeftOff = $this->checkIsLeftOff($image);
                $isRightOff = $this->checkIsRightOff($image);

                $output = [$isRed, $isGreen, $isLeftOff, $isRightOff];
                return $output;
            }
        }
        return false;
    }


    /**
     * Execute the console command.
     *
     * @return void
     * @throws \ImagickException
     */
    public function handle()
    {

        $actionQuery = Action::whereNotNull('id');

        if ($this->option('action_id') !== null) {
            $actionQuery->where('id', $this->option('action_id'));
        }

        $actions = $actionQuery->get();

        foreach ($actions as $action) {
            echo '-------------------------------' . "\n";
            echo $action->id . "\n";
            echo '-------------------------------'. "\n";


            $this->action = $action;
            $this->clearFramesDirectory();
            $this->makeFrameImages();

            if ($this->action->bout->overlay_profile !== null) {
                $profileFolder = getcwd()
                    . "/plugins/ajslim/fencingactions/overlay-profiles/"
                    . $this->action->bout->overlay_profile;
                $profileWrapper = $this->getOverlayProfileWrapper($profileFolder);
                echo "Using profile " . $profileWrapper['profile']['name'] . "\n";
            } else {
                $profileWrapper = $this->findProfile();
            }

            if ($profileWrapper === false) {
                echo "Profile not found\n";
                continue;
            }

            $this->setProfileValues($profileWrapper);

            $images = array_filter(glob($this->folder . '/*'), 'is_file');
            $foundLight = false;
            $imagesAfterLight = 1;
            foreach ($images as $imageNumber => $filename) {
                // Ignore non thumbnail files
                if (strpos($filename, 'thumb') === false) {
                    continue;
                }

                $isLight = $this->isLight($filename, $profileWrapper, $imageNumber);

                if ($isLight !== false) {

                    // We want the first frame any light comes on
                    if ($foundLight === false) {
                        echo "light on frame " . ($imageNumber + ($this->sampleRate * $this->sampleStart)) . "\n";
                        $this->action->light_frame_number = $imageNumber + ($this->sampleRate * $this->sampleStart);
                    }

                    // Other lights might come on on later frames
                    if ((bool) $isLight[0] === true) {
                        $this->action->left_coloured_light = true;
                    }
                    if ((bool) $isLight[1] === true) {
                        $this->action->right_coloured_light = true;
                    }
                    if ((bool) $isLight[2] === true) {
                        $this->action->left_off_target_light = true;
                    }
                    if ((bool) $isLight[3] === true) {
                        $this->action->right_off_target_light = true;
                    }

                    $foundLight = true;
                }

                if ($foundLight) {
                    $imagesAfterLight -= 1;
                }

                if ($foundLight && $imagesAfterLight < 0) {
                    if (is_file($filename))
                        unlink($filename); // delete file
                }
            }
            $action->save();

            // Delete images more than 2 seconds before the light
            $images = array_filter(glob($this->folder . '/*'), 'is_file');
            $totalImages = count($images);
            $clipLength = 50;
            foreach ($images as $imageNumber => $filename) {
                if ($totalImages - $clipLength > $imageNumber) {
                    if (is_file($filename))
                        unlink($filename); // delete file
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
        ];
    }

    /**
     * Get the console command options.
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['action_id', null, InputOption::VALUE_OPTIONAL, 'The Action id', null],
        ];
    }
}
