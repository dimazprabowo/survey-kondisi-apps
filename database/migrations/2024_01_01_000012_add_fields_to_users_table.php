<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->onDelete('set null');
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('position')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('position');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index('company_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['company_id', 'phone', 'position', 'is_active', 'created_by', 'updated_by']);
        });
    }
};
