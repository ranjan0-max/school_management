<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Teachers and staff share one table; `type` tells them apart.
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('employee_no', 50);
            $table->string('type', 20);
            $table->string('name');
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('qualification')->nullable();
            $table->date('joining_date')->nullable();
            $table->text('address')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['school_id', 'employee_no']);
            $table->index(['school_id', 'type', 'status']);
            $table->index(['school_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
