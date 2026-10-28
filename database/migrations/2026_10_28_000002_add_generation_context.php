<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('world_locations', fn (Blueprint $t) => $t->jsonb('generation_context')->nullable());
    }

    public function down(): void
    {
        Schema::table('world_locations', fn (Blueprint $t) => $t->dropColumn('generation_context'));
    }
};
