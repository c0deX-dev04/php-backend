<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $legacySubjectIds = DB::table('subjects')->whereIn('code', ['DS', 'DBMS', 'OS', 'WEB'])->pluck('id');
        $notePaths = DB::table('notes')->whereIn('subject_id', $legacySubjectIds)->pluck('file_path')->all();
        $seatingPaths = DB::table('exams')->whereIn('subject_id', $legacySubjectIds)
            ->whereNotNull('seating_attachment')->pluck('seating_attachment')->all();

        Storage::disk('local')->delete([...$notePaths, ...$seatingPaths]);

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role')->index();
        });

        DB::table('subjects')->whereIn('id', $legacySubjectIds)->delete();
        DB::table('labs')->whereIn('name', [
            'Data Structures Lab',
            'DBMS Lab',
            'Web Technology Lab',
        ])->delete();

        Schema::table('subjects', function (Blueprint $table) {
            $table->foreignId('teacher_id')->nullable()->after('code')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn('is_active');
        });
    }
};
