<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('none');
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_eira_id')->nullable()->index();
            $table->string('demo_label')->nullable();
            $table->timestamps();
        });

        Schema::create('studies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients');
            $table->string('folio')->unique();
            $table->string('public_reference')->unique();
            $table->string('external_form_folio')->nullable()->index();
            $table->string('external_cytology_number')->nullable()->index();
            $table->date('taken_at')->nullable();
            $table->timestamps();
        });

        Schema::create('study_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_id')->constrained('studies')->cascadeOnDelete();
            $table->string('channel', 10);
            $table->text('value');
            $table->timestamp('verified_at')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_id')->nullable()->constrained('studies');
            $table->string('type', 20);
            $table->string('status', 30)->index();
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('supersedes_id')->nullable();
            $table->string('original_path');
            $table->string('original_mime', 50);
            $table->unsignedBigInteger('original_size');
            $table->char('sha256', 64)->index();
            $table->string('delivery_path')->nullable();
            $table->char('delivery_sha256', 64)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('associated_by')->nullable()->constrained('users');
            $table->timestamp('associated_at')->nullable();
            $table->boolean('check_legible')->default(false);
            $table->boolean('check_matches_study')->default(false);
            $table->boolean('check_origin_authorization')->default(false);
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 40)->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('access_challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_id')->constrained('studies')->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('study_id')->constrained('studies')->cascadeOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('documents');
            $table->foreignUuid('study_contact_id')->nullable()->constrained('study_contacts')->nullOnDelete();
            $table->string('channel', 10);
            $table->string('purpose', 20);
            $table->string('idempotency_key')->unique();
            $table->string('status', 20);
            $table->string('provider_message_id')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 80)->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 10);
            $table->foreignId('actor_id')->nullable();
            $table->uuid('study_id')->nullable()->index();
            $table->uuid('document_id')->nullable()->index();
            $table->string('action', 50)->index();
            $table->string('result', 20);
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('delivery_notifications');
        Schema::dropIfExists('access_challenges');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('study_contacts');
        Schema::dropIfExists('studies');
        Schema::dropIfExists('patients');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
