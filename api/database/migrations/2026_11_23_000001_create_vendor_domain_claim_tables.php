<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_domain_claims', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('host', 253)->unique();
            $table->string('kind', 32);
            $table->string('company_uuid', 64)->index();
            $table->string('status', 24)->default('pending_dns')->index();
            $table->string('verification_token_hash', 64);
            $table->timestamp('verification_expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('created_by_uuid', 64)->nullable();
            $table->string('verified_by_uuid', 64)->nullable();
            $table->timestamps();
            $table->index(['company_uuid', 'status']);
        });

        Schema::create('vendor_domain_audits', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Deliberately no FK: retain the audit record when a claim is deleted.
            $table->unsignedBigInteger('vendor_domain_claim_id')->nullable()->index();
            $table->string('host', 253)->index();
            $table->string('company_uuid', 64)->index();
            $table->string('action', 32);
            $table->string('actor_uuid', 64)->nullable()->index();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_domain_audits');
        Schema::dropIfExists('vendor_domain_claims');
    }
};
