<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_group_joint_permissions', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_type', 50);
            $table->boolean('has_access')->default(false);

            $table->primary(['user_id', 'entity_id', 'entity_type'], 'user_group_joint_primary');
            $table->index(['user_id', 'has_access']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_group_joint_permissions');
    }
};
