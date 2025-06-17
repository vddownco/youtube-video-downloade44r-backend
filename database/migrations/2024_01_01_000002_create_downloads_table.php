<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('downloads', function (Blueprint $table) {
            $table->id();
            $table->string('video_id');
            $table->string('title');
            $table->string('url');
            $table->string('platform')->default('youtube');
            $table->string('quality');
            $table->string('format');
            $table->string('status')->default('pending');
            $table->integer('progress')->default(0);
            $table->string('thumbnail')->nullable();
            $table->string('duration')->nullable();
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('download_url')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['video_id', 'quality', 'format']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('downloads');
    }
};