<?php namespace Ajslim\FencingActions\Console;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Models\Fencer;
use Ajslim\Fencingactions\Models\Tournament;
use DateTime;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use October\Rain\Network\Http;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class UpdateFencerPhotos extends Command
{
    /**
     * @var string The console command name.
     */
    protected $name = 'fencingactions:updatefencerphotos';

    /**
     * @var string The console command description.
     */
    protected $description = 'No description provided yet...';

    private $temporaryTextArray;


    private function makemakeHeadToHeadUrl($fencerLastName, $fencerFirstName, $fencerFieSiteNumber)
    {
        // Use the Last name of compound last names
        $fencerLastName = str_replace(' ', '-', $fencerLastName);
        $fencerLastNameArray = explode('-', $fencerLastName);
        $fencerLastName = $fencerLastNameArray[(count($fencerLastNameArray) - 1)];

        // Use the first name of compound first names (Though I think it actually doesn't matter what the first name is)
        $fencerFirstName = str_replace(' ', '-', $fencerFirstName);
        $fencerFirstNameArray = explode('-', $fencerFirstName);
        $fencerFirstName = $fencerFirstNameArray[0];

        return "http://fie.org/fencers/$fencerLastName-$fencerFirstName-$fencerFieSiteNumber/head-to-head";
    }


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

        $gender = $this->argument('gender');
        $fencers = Fencer::where('gender', '=', $gender)->get();

        $totalFencers = count($fencers);
        foreach ($fencers as $fencerIndex => $fencer) {
            $currenUrl = $this->makemakeHeadToHeadUrl(
                $fencer->last_name,
                $fencer->first_name,
                $fencer->fie_site_number
            );
            $fencerBoutsPage = Http::get($currenUrl);

            $dom = new DOMDocument();

            // The @ suppreses warnings from bad html
            @$dom->loadHTML($fencerBoutsPage);

            $finder = new DomXPath($dom);

            $classname = "jumbotron__photo";
            $photoImgElement = $finder->query(
                "//*[contains(concat(' ', normalize-space(@class), ' '), ' $classname ')]"
            );
            if ($photoImgElement->item(0) !== null) {
                $photoUrl = $photoImgElement->item(0)->getAttribute('src');
                $fencer->photo_url = $photoUrl;
                $fencer->save();
                $this->info($fencerIndex . '/' . $totalFencers . ' - Photo Found - ' . $currenUrl);
            } else {
                $this->error($fencerIndex . '/' . $totalFencers . ' - No Photo - ' . $currenUrl);
            }
        }
    }


    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['gender', InputArgument::REQUIRED, 'The gender to get bouts from'],
        ];
    }


    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
