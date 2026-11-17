<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The HTML record scraper and its queue are gone. Recent records come from the API.
     */
    public function up(): void
    {
        Schema::dropIfExists('scraped_records_queue');
        Schema::dropIfExists('scraped_pages');
        Schema::dropIfExists('scraper_progress');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('scraper_progress', function (Blueprint $table) {
            $table->id();
            $table->integer('current_page')->default(1);
            $table->integer('detected_last_page')->nullable();
            $table->integer('records_scraped')->default(0);
            $table->timestamp('last_scrape_at')->nullable();
            $table->enum('status', ['idle', 'running', 'paused', 'completed', 'error'])->default('idle');
            $table->text('error_message')->nullable();
            $table->text('stop_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('scraped_pages', function (Blueprint $table) {
            $table->id();
            $table->integer('page_number')->unique();
            $table->integer('records_count');
            $table->string('page_fingerprint', 32);
            $table->enum('status', ['scraped', 'queued', 'processed'])->default('scraped');
            $table->timestamp('scraped_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'page_number']);
        });

        Schema::create('scraped_records_queue', function (Blueprint $table) {
            $table->id();
            $table->integer('page_number');
            $table->integer('record_index');
            $table->json('record_data');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->unique(['page_number', 'record_index']);
            $table->index(['status', 'id']);
        });
    }
};
