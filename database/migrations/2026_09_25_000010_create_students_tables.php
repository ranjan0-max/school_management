<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('admission_no', 50);
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('admission_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'admission_no']);
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'first_name']);
        });

        // Where a student studies in each academic session (history is kept per year).
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('roll_no', 20)->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'academic_session_id']);
            $table->unique(['academic_session_id', 'section_id', 'roll_no'], 'enrollments_roll_unique');
            $table->index(['school_id', 'academic_session_id', 'section_id'], 'enrollments_section_index');
        });

        Schema::create('guardian_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('relation', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['guardian_id', 'student_id']);
            $table->index(['school_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardian_student');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('students');
    }
};
