<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notices are archived, never deleted. audience = "school" (everyone) or "selected"
        // (the roles / class parents listed in notice_audiences).
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('audience', 20)->default('school');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('publish_at')->nullable();
            $table->date('expires_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('archived_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'is_pinned']);
            $table->index(['school_id', 'publish_at']);
        });

        // Who a "selected" notice is for: target_type "role" (staff holding it) or "class"
        // (parents of students in that class).
        Schema::create('notice_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('notice_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->timestamps();

            $table->unique(['notice_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['notice_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notice_audiences');
        Schema::dropIfExists('notices');
    }
};
