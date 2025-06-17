<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'title',
        'url',
        'platform',
        'quality',
        'format',
        'status',
        'file_path',
        'file_size',
        'thumbnail',
        'duration',
        'progress',
        'error_message',
        'download_url',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'file_size' => 'integer',
        'progress' => 'integer'
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_DOWNLOADING = 'downloading';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_EXPIRED = 'expired';

    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function getDownloadUrlAttribute($value)
    {
        if ($this->status === self::STATUS_COMPLETED && $this->file_path && !$this->isExpired()) {
            return url("/api/download/file/{$this->id}");
        }
        return null;
    }
}