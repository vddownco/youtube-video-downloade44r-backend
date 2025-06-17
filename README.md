# VideoGrab Backend API

Laravel backend for the VideoGrab video downloader application.

## Features

- YouTube video analysis using YouTube Data API v3
- Video downloading using yt-dlp
- Queue-based download processing
- File serving with expiration
- Download history tracking
- Automatic cleanup of expired files
- Rate limiting and CORS support

## Requirements

- PHP 8.1+
- Composer
- MySQL/PostgreSQL
- Redis (for queues and caching)
- yt-dlp (for video downloading)
- FFmpeg (for audio conversion)

## Installation

1. **Clone and setup:**
```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

2. **Configure environment:**
Edit `.env` file with your settings:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=videograb
DB_USERNAME=root
DB_PASSWORD=

REDIS_HOST=127.0.0.1
QUEUE_CONNECTION=redis

YOUTUBE_API_KEY=AIzaSyCyxF3zKOwCgxEvH0QFdWkH0lkmoGU32Ng
FRONTEND_URL=http://localhost:5173
```

3. **Install system dependencies:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install python3-pip ffmpeg
pip3 install yt-dlp

# macOS
brew install yt-dlp ffmpeg

# Windows
# Download yt-dlp.exe and ffmpeg.exe and add to PATH
```

4. **Setup database:**
```bash
php artisan migrate
```

5. **Start services:**
```bash
# Start Laravel server
php artisan serve --host=0.0.0.0 --port=8000

# Start queue worker (in another terminal)
php artisan queue:work --tries=3

# Start scheduler (in another terminal)
php artisan schedule:work
```

## API Endpoints

### Video Analysis
```http
POST /api/video/analyze
Content-Type: application/json

{
    "url": "https://www.youtube.com/watch?v=VIDEO_ID"
}
```

### Initiate Download
```http
POST /api/video/download
Content-Type: application/json

{
    "url": "https://www.youtube.com/watch?v=VIDEO_ID",
    "video_id": "VIDEO_ID",
    "title": "Video Title",
    "quality": "720p",
    "format": "mp4",
    "thumbnail": "https://...",
    "duration": "3:45"
}
```

### Check Download Status
```http
GET /api/video/status/{downloadId}
```

### Download File
```http
GET /api/download/file/{downloadId}
```

### Download History
```http
GET /api/video/history
```

## Configuration

### Download Settings
```env
DOWNLOAD_PATH=/storage/app/downloads
MAX_DOWNLOAD_SIZE=500000000
ALLOWED_FORMATS=mp4,mp3,webm,m4a
CLEANUP_AFTER_HOURS=24
```

### Queue Configuration
The application uses Redis queues for background processing. Make sure Redis is running and configured properly.

### CORS Configuration
Update `config/cors.php` to allow your frontend domain:
```php
'allowed_origins' => [
    env('FRONTEND_URL', 'http://localhost:5173'),
    // Add your production domain
],
```

## Deployment

### Production Setup

1. **Environment:**
```bash
APP_ENV=production
APP_DEBUG=false
```

2. **Optimize:**
```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Queue Worker:**
Setup supervisor or systemd to keep queue workers running:
```ini
[program:videograb-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --sleep=3 --tries=3
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
numprocs=2
```

4. **Scheduler:**
Add to crontab:
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Security

- API rate limiting (60 requests per minute)
- File access validation
- Download expiration (24 hours default)
- Input validation and sanitization
- CORS protection

## Monitoring

- Check `/api/health` for service status
- Monitor queue jobs: `php artisan queue:monitor`
- Check logs in `storage/logs/`

## Troubleshooting

### Common Issues

1. **yt-dlp not found:**
```bash
which yt-dlp
# Make sure it's in PATH
```

2. **Permission errors:**
```bash
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

3. **Queue not processing:**
```bash
php artisan queue:restart
php artisan queue:work --verbose
```

4. **Redis connection:**
```bash
redis-cli ping
# Should return PONG
```

## Support

For issues and questions, check the logs and ensure all dependencies are properly installed.