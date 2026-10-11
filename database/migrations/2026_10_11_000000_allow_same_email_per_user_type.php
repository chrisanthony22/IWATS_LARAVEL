<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The same email may have several accounts, but only one per user type.
 * Replaces the default unique(email) index with unique(email, usertype_id).
 *
 * If dropUnique fails, the index has a different name in your database: check it with
 *   SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('users');
 * and put that name in dropUnique().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->unique(['email', 'usertype_id'], 'users_email_usertype_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_usertype_unique');
            $table->unique('email', 'users_email_unique');
        });
    }
};