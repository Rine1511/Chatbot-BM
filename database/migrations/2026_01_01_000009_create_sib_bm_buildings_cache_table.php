<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sib_bm_buildings_cache', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 100)->unique();
            $table->string('name', 255)->index();
            $table->string('category', 120)->nullable()->index();
            $table->string('function_desc', 255)->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('land_area', 12, 2)->nullable();
            $table->decimal('building_area', 12, 2)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->json('facilities')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('detail_url', 500)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sib_bm_buildings_cache');
    }
};
