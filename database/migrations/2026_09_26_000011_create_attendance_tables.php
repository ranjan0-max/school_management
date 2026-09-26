<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A holiday can span several days (ends_on empty = one day). Attendance is not taken on holidays.
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'starts_on']);
        });

        // One sheet per section per day: who took it and whether it is approved.
        Schema::create('student_attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('taken_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'date']);
            $table->index(['school_id', 'date', 'status']);
        });

        // `date` repeats the sheet's date so monthly reports read one table.
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sheet_id')->constrained('student_attendance_sheets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20);
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->unique(['sheet_id', 'student_id']);
            $table->index(['school_id', 'student_id', 'date']);
        });

        // One sheet per school per day for teachers and staff.
        Schema::create('staff_attendance_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20)->default('submitted');
            $table->foreignId('taken_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'date']);
            $table->index(['school_id', 'status']);
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sheet_id')->constrained('staff_attendance_sheets')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('date');
            $table->string('status', 20);
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->unique(['sheet_id', 'employee_id']);
            $table->index(['school_id', 'employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff_attendance_sheets');
        Schema::dropIfExists('student_attendances');
        Schema::dropIfExists('student_attendance_sheets');
        Schema::dropIfExists('holidays');
    }
};
