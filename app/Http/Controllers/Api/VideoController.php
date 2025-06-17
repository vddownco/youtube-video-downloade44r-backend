<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\YouTubeService;
use App\Services\DownloadService;
use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class VideoController extends Controller
{
    protected $youtubeService;
    protected $downloadService;

    public function __construct(YouTubeService $youtubeService, DownloadService $downloadService)
    {
        $this->youtubeService = $youtubeService;
        $this->downloadService = $downloadService;
    }

    public function analyze(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid URL provided',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $url = $request->input('url');

            // Extract video ID
            $videoId = $this->youtubeService->extractVideoId($url);
            if (!$videoId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid YouTube URL. Please check the URL and try again.'
                ], 400);
            }

            // Get video information
            $videoInfo = $this->youtubeService->getVideoInfo($videoId);
            if (!$videoInfo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Video not found or is private/unavailable.'
                ], 404);
            }

            // Get available qualities
            $qualities = $this->downloadService->getAvailableQualities($videoId, $url);

            return response()->json([
                'success' => true,
                'data' => [
                    'video_info' => $videoInfo,
                    'qualities' => $qualities
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Video analysis failed', ['error' => $e->getMessage(), 'url' => $url]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze video. Please try again later.'
            ], 500);
        }
    }

    public function download(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url',
            'video_id' => 'required|string',
            'title' => 'required|string',
            'quality' => 'required|string',
            'format' => 'required|string|in:mp4,mp3,webm,m4a',
            'thumbnail' => 'nullable|url',
            'duration' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Check if download already exists and is recent
            $existingDownload = Download::where('video_id', $request->video_id)
                ->where('quality', $request->quality)
                ->where('format', $request->format)
                ->where('status', Download::STATUS_COMPLETED)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingDownload) {
                return response()->json([
                    'success' => true,
                    'message' => 'Download already available',
                    'data' => [
                        'download_id' => $existingDownload->id,
                        'status' => $existingDownload->status,
                        'progress' => $existingDownload->progress,
                        'download_url' => $existingDownload->download_url
                    ]
                ]);
            }

            // Create video info array
            $videoInfo = [
                'id' => $request->video_id,
                'title' => $request->title,
                'thumbnail' => $request->thumbnail,
                'duration' => $request->duration
            ];

            // Initiate download
            $download = $this->downloadService->initiateDownload(
                $videoInfo,
                $request->quality,
                $request->format,
                $request->url
            );

            return response()->json([
                'success' => true,
                'message' => 'Download initiated successfully',
                'data' => [
                    'download_id' => $download->id,
                    'status' => $download->status,
                    'progress' => $download->progress
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Download initiation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate download. Please try again later.'
            ], 500);
        }
    }

    public function status($downloadId)
    {
        try {
            $download = Download::find($downloadId);

            if (!$download) {
                return response()->json([
                    'success' => false,
                    'message' => 'Download not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $download->id,
                    'status' => $download->status,
                    'progress' => $download->progress,
                    'title' => $download->title,
                    'quality' => $download->quality,
                    'format' => $download->format,
                    'file_size' => $download->file_size,
                    'download_url' => $download->download_url,
                    'error_message' => $download->error_message,
                    'expires_at' => $download->expires_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Status check failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get download status'
            ], 500);
        }
    }

    public function downloadFile($downloadId) // Changed parameter name to match route
    {
        try {
            Log::info('downloadFile called', ['downloadId' => $downloadId]);
            $fileInfo = $this->downloadService->getDownloadFile($downloadId);
            
            if (!$fileInfo) {
                return response()->json([
                    'error' => 'File not found, expired, or download not completed'
                ], 404);
            }

            // Check if file actually exists on disk
            if (!file_exists($fileInfo['path'])) {
                Log::error('File not found on disk', ['path' => $fileInfo['path']]);
                return response()->json([
                    'error' => 'File not found on server'
                ], 404);
            }
            
            // Return the file as a download response
            return response()->download(
                $fileInfo['path'],
                $fileInfo['name'],
                [
                    'Content-Type' => $fileInfo['mime'],
                    'Content-Length' => $fileInfo['size']
                ]
            );
        } catch (\Exception $e) {
            Log::error('File download failed', [
                'download_id' => $downloadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // Added for better debugging
            ]);
            
            return response()->json([
                'error' => 'Failed to download file'
            ], 500);
        }
    }

    public function streamFile($id)
    {
        try {
            $fileInfo = $this->downloadService->getDownloadFile($id);

            if (!$fileInfo) {
                return response()->json([
                    'error' => 'File not found, expired, or download not completed'
                ], 404);
            }

            // Stream the file instead of forcing download
            return response()->file($fileInfo['path'], [
                'Content-Type' => $fileInfo['mime'],
                'Content-Length' => $fileInfo['size']
            ]);
        } catch (\Exception $e) {
            Log::error('File streaming failed', [
                'download_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to stream file'
            ], 500);
        }
    }

    public function history(Request $request)
    {
        try {
            $downloads = Download::orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($download) {
                    return [
                        'id' => $download->id,
                        'title' => $download->title,
                        'platform' => $download->platform,
                        'quality' => $download->quality,
                        'format' => $download->format,
                        'status' => $download->status,
                        'progress' => $download->progress,
                        'thumbnail' => $download->thumbnail,
                        'file_size' => $download->file_size,
                        'download_url' => $download->download_url,
                        'created_at' => $download->created_at,
                        'expires_at' => $download->expires_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $downloads
            ]);
        } catch (\Exception $e) {
            Log::error('History fetch failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch download history'
            ], 500);
        }
    }
}
