<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ships (master data kapal + particulars untuk laporan)
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
            // Ship particulars (dipakai BAB II laporan survey)
            $table->string('call_sign', 20)->nullable();
            $table->string('net_tonnage', 20)->nullable();
            $table->string('loa', 20)->nullable();
            $table->string('lpp', 20)->nullable();
            $table->string('breadth', 20)->nullable();
            $table->string('depth', 20)->nullable();
            $table->string('draft', 20)->nullable();
            $table->string('dwt', 20)->nullable();
            $table->string('builder')->nullable();
            $table->string('port_of_registry')->nullable();
            $table->string('hull_material', 100)->nullable();
            $table->string('class_name')->nullable();
            $table->string('class_notations')->nullable();
            $table->string('main_engine')->nullable();
            $table->string('main_engine_power')->nullable();
            $table->string('aux_engine')->nullable();
            $table->string('aux_engine_power')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('name');
        });

        // 1b. Ship certificates — repeater Status Class pada laporan (BAB II)
        Schema::create('ship_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ship_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_type');
            $table->date('last_date')->nullable();
            $table->date('next_1_date')->nullable();
            $table->date('next_2_date')->nullable();
            $table->date('postpone_date')->nullable();
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->index(['ship_id', 'order_num']);
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
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_default');
        });

        // 3. Survey template structure (categories → sub-categories → item groups → items)
        Schema::create('survey_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_template_id')->constrained()->cascadeOnDelete();
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
            $table->string('name');
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->index(['survey_sub_category_id', 'order_num']);
        });

        Schema::create('survey_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_item_group_id')->constrained()->cascadeOnDelete();
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
            // Snapshot: template hanya cetakan — survey menyimpan struktur sendiri
            // saat dibuat, sehingga perubahan template tidak merusak survey lama.
            $table->foreignId('survey_template_id')->nullable()->constrained()->nullOnDelete();
            $table->json('structure')->nullable();
            $table->date('survey_date');
            $table->string('surveyor')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->decimal('overall_cap_score', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['ship_id', 'status']);
            $table->index(['survey_template_id', 'status']);
            $table->index('survey_date');
            $table->index('survey_number');
        });

        // 5. Survey responses (per item, simpan scores + qty + dates)
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            // Tanpa FK: id merujuk snapshot surveys.structure — item template
            // boleh dihapus/diubah tanpa merusak response survey lama.
            $table->unsignedBigInteger('survey_item_id');
            $table->json('scores')->nullable();
            $table->decimal('avg_score', 5, 2)->nullable();
            $table->date('date_issued')->nullable();
            $table->date('date_expired')->nullable();
            $table->unsignedInteger('qty')->nullable();
            $table->string('specification')->nullable();
            $table->timestamps();

            $table->unique(['survey_id', 'survey_item_id']);
            $table->index('survey_id');
        });

        // 6. Group-level notes (per survey, per item group, list dinamis berurutan)
        Schema::create('survey_group_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->cascadeOnDelete();
            // Tanpa FK: id merujuk snapshot surveys.structure (lihat survey_responses).
            $table->unsignedBigInteger('survey_item_group_id');
            $table->string('note', 500);
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->index(['survey_id', 'survey_item_group_id']);
        });

        // 7. Survey reports — dokumen laporan DOCX per survey (1:1)
        Schema::create('survey_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->unique()->constrained()->cascadeOnDelete();
            // Meta laporan (cover, lembar pengesahan, referensi kontrak)
            $table->string('report_number')->nullable();
            $table->string('report_title')->nullable();
            $table->string('contract_agreement_no')->nullable();
            $table->date('contract_agreement_date')->nullable();
            $table->string('contract_appointment_no')->nullable();
            $table->date('contract_appointment_date')->nullable();
            $table->string('approval_place')->nullable();
            $table->date('approval_date')->nullable();
            $table->string('approver_name')->nullable();
            $table->string('inspector_1')->nullable();
            $table->string('inspector_2')->nullable();
            // File hasil generate (pola async Job + FileStorageService)
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['processing', 'completed', 'failed'])->nullable();
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->string('generator_version', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('file_status');
        });

        // 8. Survey report sections — blok narasi editable per section laporan
        //    key: executive_summary | general | memoranda | finding_{catId} | saran_{catId}
        Schema::create('survey_report_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_report_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->longText('content')->nullable();
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->unique(['survey_report_id', 'key']);
        });

        // 9. Dokumentasi temuan — maksimal satu foto utama per kategori snapshot survey
        Schema::create('survey_report_documentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_report_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('survey_category_id');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->enum('file_status', ['processing', 'completed', 'failed'])->nullable();
            $table->text('file_error')->nullable();
            $table->timestamp('file_processed_at')->nullable();
            $table->json('crop_data')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['survey_report_id', 'survey_category_id'], 'survey_report_documentation_category_unique');
            $table->index('file_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_report_documentations');
        Schema::dropIfExists('survey_report_sections');
        Schema::dropIfExists('survey_reports');
        Schema::dropIfExists('survey_group_notes');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('survey_items');
        Schema::dropIfExists('survey_item_groups');
        Schema::dropIfExists('survey_sub_categories');
        Schema::dropIfExists('survey_categories');
        Schema::dropIfExists('survey_templates');
        Schema::dropIfExists('ship_certificates');
        Schema::dropIfExists('ships');
    }
};
