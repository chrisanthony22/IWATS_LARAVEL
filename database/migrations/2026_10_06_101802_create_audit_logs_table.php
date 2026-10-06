<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id(); // BIGINT IDENTITY(1,1) PRIMARY KEY
            
            // Custom Columns
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255)->nullable();
            $table->string('event', 50)->index();
            $table->string('auditable_type', 150)->nullable();
            $table->string('auditable_id', 100)->nullable();
            $table->string('description', 500)->nullable();
            $table->text('old_values')->nullable(); // Maps to NVARCHAR(MAX) on SQL Server
            $table->text('new_values')->nullable(); // Maps to NVARCHAR(MAX) on SQL Server
            $table->string('url', 1000)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            
            // Explicit created_at timestamp without updated_at
            $table->timestamp('created_at', 0)->index();

            // Composite Index for auditable morph relationship
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};