<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->foreignId('school_id')->nullable()->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('all_school_menus')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'name']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
