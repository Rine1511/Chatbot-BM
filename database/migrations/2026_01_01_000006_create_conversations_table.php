<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->char('session_token', 64)->unique();
            $table->char('visitor_hash', 64)->nullable()->index();
            $table->string('origin_domain', 255)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
