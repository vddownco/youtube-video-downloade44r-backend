<?php

namespace App\Services;

use App\Models\Download;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DownloadService
{
    private $downloadPath;
    private $maxFileSize;
    private $allowedFormats;
    private $ytDlpPath;
    private $ffmpegPath;

    public function __construct()
    {
        $this->downloadPath = storage_path('app/downloads');
        $this->maxFileSize = config('downloader.max_file_size', 500000000);
        $this->allowedFormats = explode(',', config('downloader.allowed_formats', 'mp4,mp3,webm,m4a'));
        $this->ytDlpPath = config('downloader.yt_dlp_path', 'yt-dlp');
        $this->ffmpegPath = config('downloader.ffmpeg_path', 'ffmpeg');
        // Create downloads directory if it doesn't exist
        if (!file_exists($this->downloadPath)) {
            mkdir($this->downloadPath, 0755, true);
        }
    }

    public function getAvailableQualities($videoId, $url)
    {
        try {
            // First check if yt-dlp is available
            if (!$this->checkYtDlpAvailable()) {
                Log::warning('yt-dlp not available, using default qualities');
                return $this->getDefaultQualities();
            }

            // Use yt-dlp to get available formats
            $command = [
                escapeshellarg($this->ytDlpPath),
                '--list-formats',
                '--no-warnings',
                '--quiet',
                '--no-check-certificates',
                escapeshellarg($url)
            ];

            $result = Process::timeout(30)->run(implode(' ', $command));

            // Fixed: Use exitCode() instead of successful()
            if ($result->exitCode() !== 0) {
                Log::error('yt-dlp list formats failed', [
                    'error' => $result->errorOutput(),
                    'url' => $url,
                    'exit_code' => $result->exitCode()
                ]);
                return $this->getDefaultQualities();
            }

            $qualities = $this->parseFormats($result->output());
            return empty($qualities) ? $this->getDefaultQualities() : $qualities;
        } catch (\Exception $e) {
            Log::error('Error getting qualities', ['error' => $e->getMessage(), 'url' => $url]);
            return $this->getDefaultQualities();
        }
    }

    private function checkYtDlpAvailable()
    {
        try {
            $command = escapeshellarg($this->ytDlpPath) . ' --version';
            $result = Process::run($command);
            // Fixed: Use exitCode() instead of successful()
            return $result->exitCode() === 0;
        } catch (\Exception $e) {
            Log::error('YT-DLP availability check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function parseFormats($output)
    {
        $qualities = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'format code') !== false) {
                continue;
            }

            // More flexible regex for format parsing
            if (preg_match('/^(\S+)\s+(\w+)\s+.*?(\d+)p.*?(\S+)?/i', $line, $matches)) {
                $formatId = $matches[1];
                $ext = strtolower($matches[2]);
                $quality = $matches[3] . 'p';
                $filesize = isset($matches[4]) ? $matches[4] : null;

                if (in_array($ext, $this->allowedFormats)) {
                    $qualities[] = [
                        'format_id' => $formatId,
                        'quality' => $quality,
                        'format' => $ext,
                        'resolution' => $quality,
                        'filesize' => $filesize
                    ];
                }
            }
        }

        // Remove duplicates and sort by quality
        $qualities = $this->removeDuplicateQualities($qualities);

        // Always add audio-only option
        $qualities[] = [
            'format_id' => 'bestaudio',
            'quality' => 'audio',
            'format' => 'mp3',
            'resolution' => 'audio',
            'filesize' => null
        ];

        return $qualities;
    }

    private function removeDuplicateQualities($qualities)
    {
        $unique = [];
        $seen = [];

        foreach ($qualities as $quality) {
            $key = $quality['quality'] . '_' . $quality['format'];
            if (!isset($seen[$key])) {
                $unique[] = $quality;
                $seen[$key] = true;
            }
        }

        return $unique;
    }

    private function getDefaultQualities()
    {
        return [
            ['format_id' => 'best[height<=2160]', 'quality' => '2160p', 'format' => 'mp4', 'resolution' => '3840x2160', 'filesize' => '~500MB'],
            ['format_id' => 'best[height<=1080]', 'quality' => '1080p', 'format' => 'mp4', 'resolution' => '1920x1080', 'filesize' => '~200MB'],
            ['format_id' => 'best[height<=720]', 'quality' => '720p', 'format' => 'mp4', 'resolution' => '1280x720', 'filesize' => '~100MB'],
            ['format_id' => 'best[height<=480]', 'quality' => '480p', 'format' => 'mp4', 'resolution' => '854x480', 'filesize' => '~50MB'],
            ['format_id' => 'best[height<=360]', 'quality' => '360p', 'format' => 'mp4', 'resolution' => '640x360', 'filesize' => '~25MB'],
            ['format_id' => 'bestaudio', 'quality' => 'audio', 'format' => 'mp3', 'resolution' => 'audio', 'filesize' => '~5MB']
        ];
    }

    public function initiateDownload($videoInfo, $quality, $format, $url)
    {
        // Validate input data
        if (empty($videoInfo['id']) || empty($videoInfo['title'])) {
            throw new \InvalidArgumentException('Invalid video information provided');
        }

        if (!in_array($format, $this->allowedFormats)) {
            throw new \InvalidArgumentException('Invalid format specified');
        }

        $download = Download::create([
            'video_id' => $videoInfo['id'],
            'title' => $videoInfo['title'],
            'url' => $url,
            'platform' => 'youtube',
            'quality' => $quality,
            'format' => $format,
            'status' => Download::STATUS_PENDING,
            'thumbnail' => $videoInfo['thumbnail'] ?? null,
            'duration' => $videoInfo['duration'] ?? null,
            'progress' => 0,
            'expires_at' => now()->addHours(config('downloader.cleanup_after_hours', 24))
        ]);

        // Dispatch download job
        try {
            dispatch(new \App\Jobs\ProcessVideoDownload($download));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch download job', ['error' => $e->getMessage()]);
            $download->update(['status' => Download::STATUS_FAILED, 'error_message' => 'Failed to start download process']);
            throw $e;
        }

        return $download;
    }

    public function downloadVideo(Download $download)
    {
        try {
            $download->update(['status' => Download::STATUS_DOWNLOADING, 'progress' => 0]);

            // Check if yt-dlp is available
            if (!$this->checkYtDlpAvailable()) {
                throw new \Exception('yt-dlp is not installed or not available in PATH');
            }

            $filename = $this->generateFilename($download);
            $outputPath = $this->downloadPath . '/' . $filename;

            // Prepare yt-dlp command with better error handling
            $command = $this->buildDownloadCommand($download, $outputPath);

            Log::info('Starting download', [
                'download_id' => $download->id,
                'command' => implode(' ', $command)
            ]);

            // Execute download with progress tracking
            $process = Process::timeout(config('downloader.download_timeout', 3600))->start(implode(' ', $command));

            $output = '';
            $lastProgress = 0;

            while ($process->running()) {
                $latestOutput = $process->latestOutput();
                $output .= $latestOutput;

                $progress = $this->extractProgress($latestOutput);
                if ($progress > $lastProgress) {
                    $download->update(['progress' => $progress]);
                    $lastProgress = $progress;
                }

                sleep(2);
            }

            // Wait for process to complete and get the result
            $result = $process->wait();

            // Check if the process completed successfully
            if ($result->exitCode() === 0) {
                $fileSize = file_exists($outputPath) ? filesize($outputPath) : 0;

                if ($fileSize === 0) {
                    throw new \Exception('Downloaded file is empty');
                }

                $download->update([
                    'status' => Download::STATUS_COMPLETED,
                    'file_path' => $filename,
                    'file_size' => $fileSize,
                    'progress' => 100,
                    'download_url' => route('api.video.downloadfile', $download->id),
                    'stream_url' => route('api.video.stream-file', $download->id) // Optional: if you add streaming
                ]);

                Log::info('Download completed', [
                    'download_id' => $download->id,
                    'file_size' => $fileSize,
                    'file_path' => $filename
                ]);
            } else {
                $errorOutput = $result->errorOutput();
                Log::error('Download process failed', [
                    'download_id' => $download->id,
                    'error' => $errorOutput,
                    'exit_code' => $result->exitCode()
                ]);
                throw new \Exception('Download process failed: ' . $errorOutput);
            }
        } catch (\Exception $e) {
            Log::error('Download failed', [
                'download_id' => $download->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $download->update([
                'status' => Download::STATUS_FAILED,
                'error_message' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function generateFilename(Download $download)
    {
        $title = preg_replace('/[^a-zA-Z0-9\-_]/', '', Str::slug($download->title));
        $title = substr($title, 0, 50); // Limit length
        $timestamp = time();
        return "{$title}_{$download->quality}_{$timestamp}.{$download->format}";
    }

    private $ffmpegAvailable = null;

    private function isFfmpegAvailable()
    {
        if ($this->ffmpegAvailable === null) {
            try {
                $command = escapeshellarg($this->ffmpegPath) . ' -version';
                $result = Process::run($command);
                $this->ffmpegAvailable = $result->exitCode() === 0;
            } catch (\Exception $e) {
                Log::error('FFmpeg availability check failed', ['error' => $e->getMessage()]);
                $this->ffmpegAvailable = false;
            }
        }
        return $this->ffmpegAvailable;
    }

    private function buildDownloadCommand(Download $download, $outputPath)
    {
        $command = [escapeshellarg($this->ytDlpPath)];

        // Add common options
        $command[] = '--no-warnings';
        $command[] = '--newline';
        $command[] = '--no-check-certificates';
        $command[] = '--prefer-free-formats';

        // Add FFmpeg location if custom path exists
        if ($this->ffmpegPath && $this->ffmpegPath !== 'ffmpeg') {
            $command[] = '--ffmpeg-location';
            $command[] = escapeshellarg($this->ffmpegPath);
        }

        // Format selection
        if ($download->format === 'mp3' || $download->quality === 'audio') {
            if ($this->isFfmpegAvailable()) {
                $command[] = '--extract-audio';
                $command[] = '--audio-format';
                $command[] = 'mp3';
                $command[] = '--audio-quality';
                $command[] = '192K';
            } else {
                // Fallback to best audio without conversion
                $command[] = '-f';
                $command[] = 'bestaudio';
                Log::warning('FFmpeg missing: Downloading audio in original format');
            }
        } else {
            // Video format selection with fallback
            $formatSelector = $this->getFormatSelector($download->quality);
            $command[] = '-f';
            $command[] = escapeshellarg($formatSelector);
        }

        // Output options
        $command[] = '-o';
        $command[] = escapeshellarg($outputPath);
        $command[] = escapeshellarg($download->url);

        return $command;
    }

    private function getFormatSelector($quality)
    {
        switch ($quality) {
            case '2160p':
                return 'best[height<=2160]/best';
            case '1080p':
                return 'best[height<=1080]/best';
            case '720p':
                return 'best[height<=720]/best';
            case '480p':
                return 'best[height<=480]/best';
            case '360p':
                return 'best[height<=360]/best';
            default:
                return 'best';
        }
    }

    private function extractProgress($output)
    {
        // Parse yt-dlp progress output
        if (preg_match('/\[download\]\s+(\d+\.?\d*)%/', $output, $matches)) {
            return min(99, (int)$matches[1]); // Cap at 99% until completion
        }
        return 0;
    }

    public function getDownloadFile($downloadId)
    {
        $download = Download::find($downloadId);

        if (!$download) {
            Log::warning('Download not found', ['download_id' => $downloadId]);
            return null;
        }

        if ($download->status !== Download::STATUS_COMPLETED) {
            Log::warning('Download not completed', ['download_id' => $downloadId, 'status' => $download->status]);
            return null;
        }

        if ($download->isExpired()) {
            Log::info('Download expired', ['download_id' => $downloadId]);
            return null;
        }

        $filePath = $this->downloadPath . '/' . $download->file_path;

        if (!file_exists($filePath)) {
            Log::error('Download file not found', ['download_id' => $downloadId, 'path' => $filePath]);
            return null;
        }

        return [
            'path' => $filePath,
            'name' => $download->file_path,
            'size' => $download->file_size,
            'mime' => $this->getMimeType($download->format)
        ];
    }

    private function getMimeType($format)
    {
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            'webm' => 'video/webm',
            'm4a' => 'audio/mp4'
        ];

        return $mimeTypes[$format] ?? 'application/octet-stream';
    }

    public function cleanupExpiredDownloads()
    {
        try {
            $expiredDownloads = Download::where('expires_at', '<', now())
                ->where('status', Download::STATUS_COMPLETED)
                ->get();

            $cleanedCount = 0;
            foreach ($expiredDownloads as $download) {
                $filePath = $this->downloadPath . '/' . $download->file_path;

                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        $cleanedCount++;
                    }
                }

                $download->update(['status' => Download::STATUS_EXPIRED]);
            }

            Log::info('Cleaned up expired downloads', ['count' => $cleanedCount]);
            return $cleanedCount;
        } catch (\Exception $e) {
            Log::error('Cleanup failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }
}
