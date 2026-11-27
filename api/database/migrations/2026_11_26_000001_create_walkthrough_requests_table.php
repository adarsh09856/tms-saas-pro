<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('walkthrough_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 160);
            $table->string('company', 180);
            $table->string('email', 254)->index();
            $table->string('phone', 40)->nullable();
            $table->timestamp('consent_at');
            $table->string('status', 24)->default('new')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('walkthrough_request_status_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('walkthrough_request_id')->index();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->string('actor_uuid', 64)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walkthrough_request_status_audits');
        Schema::dropIfExists('walkthrough_requests');
    }
};
