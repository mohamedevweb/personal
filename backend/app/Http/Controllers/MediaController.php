<?php

namespace App\Http\Controllers;

use App\Models\ContentPost;
use App\Models\Creator;
use App\Models\InstagramAccount;
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

    public function contentItem(ContentPost $content, int $position, InstagramMediaProxy $media): Response
    {
        $sourceUrl = $content->media_urls[$position] ?? null;
        abort_unless(is_string($sourceUrl), 404);

        $response = $media->response($sourceUrl, "content:{$content->id}:{$position}");
        abort_if($response === null, 404);

        return $response;
    }

    public function creator(Creator $creator, InstagramMediaProxy $media): Response
    {
        $response = $media->response((string) $creator->avatar_url, "creator:{$creator->id}");
        abort_if($response === null, 404);

        return $response;
    }

    public function instagramAccount(InstagramAccount $account, InstagramMediaProxy $media): Response
    {
        $response = $media->response((string) $account->profile_picture_url, "instagram-account:{$account->id}");
        abort_if($response === null, 404);

        return $response;
    }
}
