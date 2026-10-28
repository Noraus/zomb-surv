<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');
        Schema::create('worlds', function (Blueprint $t) {
            $t->id();
            $t->string('dataset')->unique();
            $t->string('seed');
            $t->unsignedBigInteger('minutes')->default(480);
        });
        Schema::create('resource_definitions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->string('name');
            $t->string('group');
            $t->decimal('weight', 8, 3);
            $t->string('rarity');
            $t->jsonb('properties');
        });
        Schema::create('world_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('world_id')->constrained();
            $t->string('stable_id');
            $t->string('name');
            $t->string('category');
            $t->string('kind');
            $t->string('source');
            $t->string('rule');
            $t->decimal('confidence', 3, 2);
            $t->string('osm_type')->nullable();
            $t->bigInteger('osm_id')->nullable();
            $t->jsonb('tags');
            $t->jsonb('geometry')->nullable();
            $t->double('lat');
            $t->double('lon');
            $t->unsignedSmallInteger('risk');
            $t->unsignedInteger('visits')->default(0);
            $t->unsignedBigInteger('last_visit')->nullable();
            $t->unique(['world_id', 'stable_id']);
            $t->index(['world_id', 'category']);
        });
        DB::statement('ALTER TABLE world_locations ADD COLUMN position geometry(Point,4326)');
        DB::statement('ALTER TABLE world_locations ADD COLUMN shape geometry(Geometry,4326)');
        DB::statement('CREATE INDEX world_locations_position_gist ON world_locations USING GIST(position)');
        DB::statement('CREATE INDEX world_locations_shape_gist ON world_locations USING GIST(shape)');
        Schema::create('location_resources', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained('world_locations')->cascadeOnDelete();
            $t->string('resource_id');
            $t->foreign('resource_id')->references('id')->on('resource_definitions');
            $t->integer('quantity');
            $t->unique(['location_id', 'resource_id']);
        });
        Schema::create('players', function (Blueprint $t) {
            $t->id();
            $t->foreignId('world_id')->unique()->constrained();
            $t->string('name');
            $t->double('lat');
            $t->double('lon');
            $t->foreignId('location_id')->nullable()->constrained('world_locations')->nullOnDelete();
            $t->unsignedSmallInteger('health')->default(100);
            $t->unsignedSmallInteger('energy')->default(100);
            $t->unsignedSmallInteger('hunger')->default(15);
            $t->unsignedSmallInteger('thirst')->default(10);
            $t->decimal('capacity', 8, 2);
            $t->unsignedBigInteger('survived')->default(0);
        });
        Schema::create('player_inventory', function (Blueprint $t) {
            $t->id();
            $t->foreignId('player_id')->constrained()->cascadeOnDelete();
            $t->string('resource_id');
            $t->foreign('resource_id')->references('id')->on('resource_definitions');
            $t->integer('quantity');
            $t->unique(['player_id', 'resource_id']);
        });
        Schema::create('events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('world_id')->constrained();
            $t->foreignId('player_id')->constrained();
            $t->foreignId('location_id')->nullable()->constrained('world_locations')->cascadeOnDelete();
            $t->string('type');
            $t->unsignedBigInteger('minute');
            $t->jsonb('result');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('osm_imports', function (Blueprint $t) {
            $t->id();
            $t->string('status');
            $t->jsonb('bbox');
            $t->integer('count')->default(0);
            $t->text('message')->nullable();
            $t->timestamps();
        });
        DB::statement('ALTER TABLE location_resources ADD CONSTRAINT stock_nonnegative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE player_inventory ADD CONSTRAINT inventory_nonnegative CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        foreach (['osm_imports', 'events', 'player_inventory', 'players', 'location_resources', 'world_locations', 'resource_definitions', 'worlds'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
