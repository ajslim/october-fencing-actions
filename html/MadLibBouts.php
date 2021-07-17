<?php namespace Ajslim\Fencingactions\Html;

use Ajslim\FencingActions\Models\Bout;
use Ajslim\FencingActions\Plugin;
use Cms\Classes\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Response;

class MadLibBouts extends Controller
{
    public function index()
    {

        $folder = 'storage/madlibs';

        $random = rand(2,99);

        $filename = '/' . $folder . '/output-' . $random . '.mp4';

        echo "<video id=\"video\" controls=\"\" loop=\"\" autoplay=\"true\" playsinline=\"\" preload=\"auto\" tabindex=\"-1\" data-vscid=\"xkoxgzidu\">
                <source src=\"$filename\" type=\"video/mp4\">
            </video>";
    }

    public function index2()
    {
        $folder = 'storage/madlibs';

        $random = rand(2,99);

        $filename = '/' . $folder . '/output-' . $random  . '.mp4';

        $file_name = getcwd() . $filename;
        $fileContents = file_get_contents($file_name);
        $filesize = filesize($file_name);
        $response = Response::make($fileContents, 200);
        $response->header('Test', $filename);
        $response->header('Accept-Ranges', 'none');
        $response->header('Content-Length', $filesize);
        $response->header('Content-Type', "video/mp4");
        return $response;
    }
}
