<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIONS = ['view', 'create', 'edit', 'delete', 'export', 'approve'];

    public function up(): void
    {
        // Top-level rows (parent_id NULL) are sidebar groups; their children are the pages.
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->string('route_name')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('actions', 120)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        Schema::create('school_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'menu_id']);
            $table->index('menu_id');
        });

        Schema::create('role_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            foreach (self::ACTIONS as $action) {
                $table->boolean('can_'.$action)->default(false);
            }

            $table->timestamps();

            $table->unique(['role_id', 'menu_id']);
            $table->index('menu_id');
        });

        // NULL = follow the role, true = extra grant, false = explicit deny.
        Schema::create('user_menu_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            foreach (self::ACTIONS as $action) {
                $table->boolean('can_'.$action)->nullable();
            }

            $table->timestamps();

            $table->unique(['user_id', 'menu_id']);
            $table->index('menu_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_menu_overrides');
        Schema::dropIfExists('role_menus');
        Schema::dropIfExists('school_menus');
        Schema::dropIfExists('menus');
    }
};
