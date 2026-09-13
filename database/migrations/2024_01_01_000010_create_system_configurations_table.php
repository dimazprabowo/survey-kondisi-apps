<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table untuk konfigurasi system (thresholds, SLA, etc)
     */
    public function up(): void
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('category', 50)->default('general'); // general, sla, threshold, notification
            $table->text('value');
            $table->string('data_type', 20)->default('string'); // string, integer, boolean, json, array
            $table->text('description')->nullable();
            $table->boolean('is_editable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('key');
            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};
