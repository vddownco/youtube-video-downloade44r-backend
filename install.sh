#!/bin/bash

echo "🚀 Installing VideoGrab Backend..."

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

# Install PHP dependencies
echo "📦 Installing PHP dependencies..."
composer install

# Copy environment file
if [ ! -f .env ]; then
    echo "📝 Creating environment file..."
    cp .env.example .env
    php artisan key:generate
fi

# Check if yt-dlp is installed
if ! command -v yt-dlp &> /dev/null; then
    echo "⚠️  yt-dlp is not installed. Installing..."
    
    # Detect OS and install yt-dlp
    if [[ "$OSTYPE" == "linux-gnu"* ]]; then
        # Linux
        sudo apt update
        sudo apt install -y python3-pip ffmpeg
        pip3 install yt-dlp
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        if command -v brew &> /dev/null; then
            brew install yt-dlp ffmpeg
        else
            echo "❌ Homebrew not found. Please install yt-dlp manually."
            exit 1
        fi
    else
        echo "❌ Unsupported OS. Please install yt-dlp manually."
        exit 1
    fi
fi

# Check if Redis is running
if ! redis-cli ping &> /dev/null; then
    echo "⚠️  Redis is not running. Please start Redis server."
    echo "   Ubuntu/Debian: sudo systemctl start redis-server"
    echo "   macOS: brew services start redis"
fi

# Create downloads directory
mkdir -p storage/app/downloads
chmod 755 storage/app/downloads

echo "✅ Installation completed!"
echo ""
echo "📋 Next steps:"
echo "1. Configure your .env file with database and API settings"
echo "2. Run: php artisan migrate"
echo "3. Start the server: php artisan serve"
echo "4. Start queue worker: php artisan queue:work"
echo ""
echo "🔗 API will be available at: http://localhost:8000/api"

# composer install
# copy .env.example .env
# php artisan key:generate
# mkdir storage\app\downloads
# php artisan migrate
# php artisan serve
# php artisan queue:work