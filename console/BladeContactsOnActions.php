<?php namespace Ajslim\Fencingactions\Console;

use Ajslim\FencingActions\Models\Action;
use Ajslim\FencingActions\Models\Bout;
use Illuminate\Console\Command;
use Imagick;
use Symfony\Component\Console\Input\InputOption;


class BladeContactsOnActions extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:bladecontactsonactions';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';



    /**
     * @param $folderString
     * @return string
     */
    private function createFolderIfNotExist($folderString)
    {
        $folder = getcwd() . $folderString;

        // make the bout directory if needed
        if (!file_exists($folderString)) {
            echo "creating $folder\n";

            mkdir($folderString);
        }
        return $folder;
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $actionId = $this->option('action-id');
        if ($actionId !== null) {
            $actions = Action::where('id', $actionId)->get();
        } else {
            $actions = Action::where('id', '>', 429)->get();
        }

        $count = $actions->count();
        $current = 0;
        $failed = [];
        foreach ($actions as $action) {
            $current += 1;
            echo $current . '/' . $count . "\n";
            echo "-----------------------------\n";
            echo $action->id . "\n";
            echo "-----------------------------\n";

            $audioUrl = str_replace('mp4', 'm4a', str_replace('clips', 'audio', $action->video_url));

            $urlLength = strlen($action->bout->video_url);
            $youtubeId = substr($action->bout->video_url, $urlLength - 11);

            $this->createFolderIfNotExist('storage/bout/' . $youtubeId . '/combined');

            $command = "ffmpeg -i ." . $audioUrl . " -i ." . $action->video_url . " -y -c:v copy -c:a aac storage/bout/" . $youtubeId . "/combined/" . $action->time . '.mp4';
            echo exec($command);

            $command = "ffmpeg -i ." . $audioUrl . " -y -af \"highpass=20000\" /tmp/highpass.m4a";
            echo exec($command);

            $command = "python3 " . getcwd() . '/../bladecontacts/bladecontacts.py /tmp/highpass.m4a';
            $results = exec($command);

            try {
                $action->blade_contacts = $results;
                $action->save();
            } catch (\Exception $e) {
                echo "============================================\n";
                echo "============================================\n";
                echo $e->getMessage() . "\n";
                echo $command . "\n";
                echo "============================================\n";
                echo "============================================\n";
            }

            echo $audioUrl . "\n";
            echo $results . "\n";
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
        ];
    }
}
