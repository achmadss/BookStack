<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP INDEX users_employee_id_index');

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->unique()->nullable()->change();
        });

        DB::statement('ALTER TABLE users ADD CONSTRAINT users_employee_id_not_email CHECK (employee_id NOT LIKE \'%@%\')');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['users_employee_id_unique']);
            $table->string('employee_id')->index()->nullable()->change();
        });
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_employee_id_not_email');
    }
};
