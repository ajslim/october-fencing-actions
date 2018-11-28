<?php
/**
 * API.php
 * The api json frontend controller
 */

namespace Ajslim\FencingActions\Api;

use Ajslim\FencingActions\Models\Action;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Api Controller
 */
class Video extends Api
{
    /**
     * The index controller
     *
     * @return Response image
     */
    public function index($actionId) {
        $action = Action::find($actionId);
        $file_name = getcwd() . $action->video_url;
        $fileContents = file_get_contents($file_name);
        $response = Response::make($fileContents, 200);
        $response->header('Content-Type', "video/mp4");
        return $response;
    }
}
