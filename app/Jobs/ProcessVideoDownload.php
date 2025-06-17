<?php

namespace App\Jobs;

use App\Models\Download;
use App\Services\DownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessVideoDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $download;
    public $timeout = 1800; // 30 minutes
    public $tries = 3;

    public function __construct(Download $download)
    {
        $this->download = $download;
    }

    public function handle(DownloadService $downloadService)
    {
        Log::info('Processing video download', ['download_id' => $this->download->id]);
        
        $downloadService->downloadVideo($this->download);
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Download job failed', [
            'download_id' => $this->download->id,
            'error' => $exception->getMessage()
        ]);

        $this->download->update([
            'status' => Download::STATUS_FAILED,
            'error_message' => $exception->getMessage()
        ]);
    }
}