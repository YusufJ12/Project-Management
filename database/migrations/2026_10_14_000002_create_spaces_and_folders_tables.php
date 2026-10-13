<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default('active'); // active, archived, completed
            $table->date('starts_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('space_members', function (Blueprint $table) {
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // manager, member, viewer
            $table->timestamp('joined_at')->useCurrent();
            $table->primary(['space_id', 'user_id']);
        });

        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
        Schema::dropIfExists('space_members');
        Schema::dropIfExists('spaces');
    }
};

