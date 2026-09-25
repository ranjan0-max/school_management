<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('code', 50)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('locale', 10)->default('en');
            $table->char('currency', 3)->default('INR');
            $table->string('status', 20)->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            $table->unsignedInteger('max_students')->nullable();
            $table->unsignedInteger('max_staff')->nullable();
            $table->decimal('price_per_student', 12, 2)->nullable();
            $table->string('billing_cycle', 20)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['billing_cycle', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
