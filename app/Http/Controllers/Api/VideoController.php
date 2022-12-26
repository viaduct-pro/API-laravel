<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Iman\Streamer\VideoStreamer;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VideoController extends Controller
{
    public function streamVideo() {
        $video_path = storage_path('video/file_example.mp4');
        VideoStreamer::streamFile($video_path);
    }

    public function streamVideoByUuid($uuid, Request $request) {
        $file = Media::where('uuid', $uuid)->first();
        return $file->toInlineResponse($request);
    }
}
