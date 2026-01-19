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
        Schema::create('user_group_content', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_group_id');
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_type', 50);
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_group_id', 'entity_id', 'entity_type'], 'unique_group_content');
            $table->index(['entity_id', 'entity_type']);
            $table->index('user_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_group_content');
    }
};
