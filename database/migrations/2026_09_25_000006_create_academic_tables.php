<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 50);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'is_current']);
            $table->index(['school_id', 'starts_on']);
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['school_id', 'sort_order']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'name']);
            $table->index(['school_id', 'class_id']);
        });

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('code', 30)->nullable();
            $table->string('type', 20)->default('theory');
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->unique(['school_id', 'code']);
            $table->index(['school_id', 'type']);
        });

        Schema::create('class_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['class_id', 'subject_id']);
            $table->index(['school_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subjects');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('academic_sessions');
    }
};
