<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff accounts belong to one branch. Owners keep branch_id empty (NULL)
 * and can see every branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'branch_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $t) {
            $t->integer('branch_id')->nullable();
            $t->foreign('branch_id')
              ->references('branch_id')->on('branches')
              ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'branch_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['branch_id']);
            $t->dropColumn('branch_id');
        });
    }
};
