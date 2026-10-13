<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->nullable()->unique()->after('email');
            });

            // Beri username default untuk pengguna yang sudah ada
            DB::table('users')->where('email', 'admin@nexsphere.web.id')->update(['username' => 'admin']);
            DB::table('users')->where('email', 'budi@nexsphere.web.id')->update(['username' => 'budi']);
            DB::table('users')->where('email', 'siti@nexsphere.web.id')->update(['username' => 'siti']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('username');
            });
        }
    }
};

