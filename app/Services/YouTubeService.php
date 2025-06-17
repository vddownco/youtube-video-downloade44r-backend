<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeService
{
    private $apiKey;
    private $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        $this->apiKey = config('services.youtube.api_key');
    }

    public function extractVideoId($url)
    {
        // More comprehensive URL patterns for YouTube
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]+)/',
            '/(?:https?:\/\/)?(?:m\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/' // Mobile URLs
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function getVideoInfo($videoId)
    {
        try {
            // Check if API key is configured
            if (empty($this->apiKey)) {
                Log::warning('YouTube API key not configured, falling back to basic info');
                return $this->getBasicVideoInfo($videoId);
            }

            $response = Http::timeout(30)->get("{$this->baseUrl}/videos", [
                'part' => 'snippet,statistics,contentDetails',
                'id' => $videoId,
                'key' => $this->apiKey
            ]);

            if (!$response->successful()) {
                Log::error('YouTube API Error', [
                    'status' => $response->status(), 
                    'body' => $response->body(),
                    'video_id' => $videoId
                ]);
                
                // Fallback to basic info if API fails
                return $this->getBasicVideoInfo($videoId);
            }

            $data = $response->json();

            if (empty($data['items'])) {
                Log::warning('Video not found in YouTube API', ['video_id' => $videoId]);
                return null;
            }

            $video = $data['items'][0];
            $snippet = $video['snippet'];
            $statistics = $video['statistics'] ?? [];
            $contentDetails = $video['contentDetails'] ?? [];

            return [
                'id' => $videoId,
                'title' => $snippet['title'] ?? 'Unknown Title',
                'description' => $snippet['description'] ?? '',
                'thumbnail' => $this->getBestThumbnail($snippet['thumbnails'] ?? []),
                'duration' => $this->formatDuration($contentDetails['duration'] ?? 'PT0S'),
                'view_count' => $this->formatViewCount($statistics['viewCount'] ?? 0),
                'channel_title' => $snippet['channelTitle'] ?? 'Unknown Channel',
                'published_at' => isset($snippet['publishedAt']) ? 
                    date('Y-m-d', strtotime($snippet['publishedAt'])) : date('Y-m-d')
            ];
        } catch (\Exception $e) {
            Log::error('YouTube Service Error', [
                'error' => $e->getMessage(),
                'video_id' => $videoId,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return basic info as fallback
            return $this->getBasicVideoInfo($videoId);
        }
    }

    private function getBasicVideoInfo($videoId)
    {
        // Fallback method when API is not available
        return [
            'id' => $videoId,
            'title' => 'YouTube Video',
            'description' => '',
            'thumbnail' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
            'duration' => '0:00',
            'view_count' => '0 views',
            'channel_title' => 'Unknown Channel',
            'published_at' => date('Y-m-d')
        ];
    }

    private function getBestThumbnail($thumbnails)
    {
        // Priority order for thumbnail quality
        $priorities = ['maxres', 'high', 'medium', 'default'];
        
        foreach ($priorities as $quality) {
            if (isset($thumbnails[$quality]['url'])) {
                return $thumbnails[$quality]['url'];
            }
        }
        
        return null;
    }

    private function formatDuration($duration)
    {
        try {
            if (preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches)) {
                $hours = isset($matches[1]) ? (int)$matches[1] : 0;
                $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
                $seconds = isset($matches[3]) ? (int)$matches[3] : 0;

                if ($hours > 0) {
                    return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
                }
                return sprintf('%d:%02d', $minutes, $seconds);
            }
        } catch (\Exception $e) {
            Log::warning('Duration parsing failed', ['duration' => $duration, 'error' => $e->getMessage()]);
        }
        
        return '0:00';
    }

    private function formatViewCount($viewCount)
    {
        try {
            $count = (int)$viewCount;
            if ($count >= 1000000000) {
                return number_format($count / 1000000000, 1) . 'B views';
            } elseif ($count >= 1000000) {
                return number_format($count / 1000000, 1) . 'M views';
            } elseif ($count >= 1000) {
                return number_format($count / 1000, 1) . 'K views';
            }
            return number_format($count) . ' views';
        } catch (\Exception $e) {
            Log::warning('View count formatting failed', ['view_count' => $viewCount, 'error' => $e->getMessage()]);
            return '0 views';
        }
    }

    /**
     * Validate if a YouTube URL is accessible
     */
    public function validateUrl($url)
    {
        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            return false;
        }

        // Try to get video info to validate
        $info = $this->getVideoInfo($videoId);
        return $info !== null;
    }
}