<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role based restrictions: the list of modules and what each user type may do in them.
 * The hasTable() checks let this run safely where you already created the tables by hand
 * (for example your local database): it skips them and just records the migration as done.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('IWMS_Modules')) {
            Schema::create('IWMS_Modules', function (Blueprint $table) {
                $table->id('ID');
                $table->string('ModuleKey', 50)->unique();     // 'users', 'audit-logs', 'agent-sales'
                $table->string('ModuleName', 100);             // shown on the Module Access screen
                $table->integer('SortOrder')->default(0);
            });
        }

        if (! Schema::hasTable('IWMS_UserTypeAccess')) {
            Schema::create('IWMS_UserTypeAccess', function (Blueprint $table) {
                $table->integer('UserTypeID');                 // IWMS_UserTypes.ID
                $table->unsignedBigInteger('ModuleID');        // IWMS_Modules.ID
                $table->boolean('CanView')->default(false);
                $table->boolean('CanCreate')->default(false);
                $table->boolean('CanEdit')->default(false);
                $table->boolean('CanDelete')->default(false);

                $table->primary(['UserTypeID', 'ModuleID']);
                $table->foreign('UserTypeID')->references('ID')->on('IWMS_UserTypes')->cascadeOnDelete();
                $table->foreign('ModuleID')->references('ID')->on('IWMS_Modules')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('IWMS_UserTypeAccess');
        Schema::dropIfExists('IWMS_Modules');
    }
};