<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ships (master data kapal)
        Schema::create('ships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique()->nullable();
            $table->integer('year_built')->nullable();
            $table->string('imo_number', 20)->nullable();
            $table->string('ship_type')->nullable();
            $table->string('flag')->nullable();
            $table->string('gross_tonnage', 20)->nullable();
            $table->string('owner')->nullable();
            $table->string('operator')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('name');
        });

        // 2. Survey templates (dapat dikelola admin, satu template = satu set struktur)
        Schema::create('survey_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_default');
        });

        // 3. Survey template structure (categories → sub-categories → item groups → items)
        Schema::create('survey_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_template_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10);
            $table->string('label');
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->index(['survey_template_id', 'order_num']);
        });

        Schema::create('survey_sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_category_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order_num')->default(0);
            $table->string('name');
            $table->timestamps();

            $table->index(['survey_category_id', 'order_num']);
        });

        Schema::create('survey_item_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_sub_category_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->index(['survey_sub_category_id', 'order_num']);
        });

        Schema::create('survey_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_item_group_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->enum('item_type', ['score', 'inventory'])->default('score');
            $table->json('score_labels')->nullable();
            $table->boolean('has_date_fields')->default(false);
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->index(['survey_item_group_id', 'order_num']);
        });

        // 4. Survey instances (per kapal, per template)
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('survey_number', 50)->unique();
            $table->foreignId('ship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_template_id')->constrained()->cascadeOnDelete();
            $table->date('survey_date');
            $table->string('surveyor')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->decimal('overall_cap_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ship_id', 'status']);
            $table->index(['survey_template_id', 'status']);
            $table->index('survey_date');
            $table->index('survey_number');
        });

        // 4. Survey responses (per item, simpan scores + notes + dates)
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('survey_item_id')->constrained()->cascadeOnDelete();
            $table->json('scores')->nullable();
            $table->decimal('avg_score', 5, 2)->nullable();
            $table->date('date_issued')->nullable();
            $table->date('date_expired')->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->string('specification')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'survey_item_id']);
            $table->index('survey_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('survey_items');
        Schema::dropIfExists('survey_item_groups');
        Schema::dropIfExists('survey_sub_categories');
        Schema::dropIfExists('survey_categories');
        Schema::dropIfExists('survey_templates');
        Schema::dropIfExists('ships');
    }
};
