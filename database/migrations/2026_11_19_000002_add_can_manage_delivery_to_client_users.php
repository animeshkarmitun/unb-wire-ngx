<?php

use App\Models\ClientUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COS-FTP-MGMT: marks the portal user allowed to manage the client's FTP
 * channel (owner decision: the client's admin user only). Backfills the
 * first active portal user of each client as the channel manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_users', function (Blueprint $table) {
            $table->boolean('can_manage_delivery')->default(false)->after('client_role_id');
        });

        // Backfill: the first active portal user of each client becomes the
        // FTP channel manager (portable across pgsql/sqlite).
        ClientUser::where('status', 'active')
            ->orderBy('id')
            ->get()
            ->groupBy('client_id')
            ->each(function ($users) {
                DB::table('client_users')->where('id', $users->first()->id)->update(['can_manage_delivery' => true]);
            });
    }

    public function down(): void
    {
        Schema::table('client_users', function (Blueprint $table) {
            $table->dropColumn('can_manage_delivery');
        });
    }
};
