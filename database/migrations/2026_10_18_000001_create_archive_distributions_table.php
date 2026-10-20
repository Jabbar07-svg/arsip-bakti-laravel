<?php

use App\Support\LocalDatabaseMigrationGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();

        if (Schema::hasTable('archive_distributions')) {
            return;
        }

        Schema::create('archive_distributions', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('archive_id');
            $table->string('origin_division', 150);
            $table->string('destination_division', 150);
            $table->unsignedInteger('requested_by');
            $table->unsignedInteger('sender_id')->nullable();
            $table->unsignedInteger('recipient_id');
            $table->unsignedInteger('approved_by')->nullable();
            $table->unsignedInteger('dispatched_by')->nullable();
            $table->unsignedInteger('received_by')->nullable();
            $table->string('status', 40)->default('Menunggu Persetujuan');
            $table->text('notes')->nullable();
            $table->text('rejection_note')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->text('receipt_note')->nullable();
            $table->string('receipt_attachment', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_archive_distributions_status');
            $table->index(['origin_division', 'destination_division'], 'idx_archive_distributions_divisions');
            $table->foreign('archive_id', 'fk_archive_distributions_archive')->references('id')->on('arsip');
            $table->foreign('requested_by', 'fk_archive_distributions_requester')->references('id')->on('users');
            $table->foreign('sender_id', 'fk_archive_distributions_sender')->references('id')->on('users')->nullOnDelete();
            $table->foreign('recipient_id', 'fk_archive_distributions_recipient')->references('id')->on('users');
            $table->foreign('approved_by', 'fk_archive_distributions_approver')->references('id')->on('users')->nullOnDelete();
            $table->foreign('dispatched_by', 'fk_archive_distributions_dispatcher')->references('id')->on('users')->nullOnDelete();
            $table->foreign('received_by', 'fk_archive_distributions_receiver')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        LocalDatabaseMigrationGuard::assertApplicationDatabase();
    }
};
