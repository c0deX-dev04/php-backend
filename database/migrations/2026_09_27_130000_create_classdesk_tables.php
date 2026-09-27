<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 10);
            $table->timestamps();
            $table->unique(['user_id', 'subject_id', 'date']);
            $table->index(['subject_id', 'date']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type', 10);
            $table->timestamps();
        });

        Schema::create('labs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('problem_statement');
            $table->dateTime('deadline');
            $table->timestamps();
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room');
            $table->timestamps();
            $table->index(['subject_id', 'exam_date']);
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('attachment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('lab_assignments');
        Schema::dropIfExists('labs');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('subjects');
    }
};
