<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The school's daily bell schedule: Period 1, Period 2, Lunch...
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 50);
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('is_break')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'sort_order']);
        });

        // One cell of a section's weekly timetable for one academic session.
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('day');
            $table->foreignId('period_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('employees')->cascadeOnUpdate()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['academic_session_id', 'section_id', 'day', 'period_id'], 'timetable_slot_unique');
            // A teacher cannot be in two sections at the same time (NULL teachers are ignored).
            $table->unique(['academic_session_id', 'teacher_id', 'day', 'period_id'], 'timetable_teacher_unique');
            $table->index(['school_id', 'academic_session_id', 'section_id'], 'timetable_section_index');
            $table->index('subject_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
        Schema::dropIfExists('periods');
    }
};
