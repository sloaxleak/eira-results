<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->dropUnique(['public_reference']);
        });

        Schema::table('studies', function (Blueprint $table) {
            $table->dropColumn('public_reference');
        });
    }

    public function down(): void
    {
        Schema::table('studies', function (Blueprint $table) {
            $table->string('public_reference')->nullable()->unique();
        });
    }
};
