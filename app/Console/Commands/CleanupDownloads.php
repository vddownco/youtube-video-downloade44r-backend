<?php

namespace App\Console\Commands;

use App\Services\DownloadService;
use Illuminate\Console\Command;

class CleanupDownloads extends Command
{
    protected $signature = 'downloads:cleanup';
    protected $description = 'Clean up expired download files';

    public function handle(DownloadService $downloadService)
    {
        $this->info('Starting download cleanup...');
        
        $downloadService->cleanupExpiredDownloads();
        
        $this->info('Download cleanup completed.');
    }
}