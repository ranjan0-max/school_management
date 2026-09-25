<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Parents/guardians. One guardian can be linked to several students (siblings).
        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->cascadeOnUpdate()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'name']);
            $table->index(['school_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guardians');
    }
};
