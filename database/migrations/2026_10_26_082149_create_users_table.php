<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Website login accounts with an owner/staff role. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('username', 50)->unique();
            $t->string('password');
            $t->string('role', 10)->default('staff');
            $t->rememberToken();
            $t->timestamps();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('owner', 'staff'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};