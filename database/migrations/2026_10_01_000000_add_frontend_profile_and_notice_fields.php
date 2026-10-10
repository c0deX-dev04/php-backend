<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('roll_number')->nullable()->unique()->after('role');
        });

        Schema::table('notices', function (Blueprint $table) {
            $table->string('category', 80)->default('General')->after('content');
            $table->boolean('pinned')->default(false)->after('category');
        });

        Schema::table('exams', function (Blueprint $table) {
            $table->string('seating_attachment')->nullable()->after('room');
        });

        Schema::create('attendance_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('minimum_percentage')->default(75);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_thresholds');

        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('seating_attachment');
        });

        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn(['category', 'pinned']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['roll_number']);
            $table->dropColumn('roll_number');
        });
    }
};
