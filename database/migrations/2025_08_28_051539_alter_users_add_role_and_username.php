<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            // agrega role_id y username si no existen
            if (!Schema::hasColumn('users','role_id')) {
                $t->foreignId('role_id')->after('id')->constrained('roles')->restrictOnDelete();
            }
            if (!Schema::hasColumn('users','username')) {
                $t->string('username')->unique()->after('role_id');
            }
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users','username')) {
                $t->dropUnique(['username']);
                $t->dropColumn('username');
            }
            if (Schema::hasColumn('users','role_id')) {
                $t->dropConstrainedForeignId('role_id');
            }
        });
    }
};
