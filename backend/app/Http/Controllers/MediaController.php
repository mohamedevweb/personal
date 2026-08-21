<?php

namespace App\Http\Controllers;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Services\InstagramMediaProxy;
use Illuminate\Http\Response;

class MediaController extends Controller
{
    public function content(ContentPost $content, InstagramMediaProxy $media): Response
    {
        $response = $media->response((string) $content->thumbnail_url, "content:{$content->id}");
        abort_if($response === null, 404);

        return $response;
    }

    public function creator(Creator $creator, InstagramMediaProxy $media): Response
    {
        $response = $media->response((string) $creator->avatar_url, "creator:{$creator->id}");
        abort_if($response === null, 404);

        return $response;
    }
}
