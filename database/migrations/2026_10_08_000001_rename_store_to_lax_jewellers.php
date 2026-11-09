<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The store is "Lax Jewellers" (matching laxjewellers.com), not "Lux".
 * Renames the stored name and payment-account holder names; values the
 * admin has already changed to something else are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'store_name')->where('value', 'Lux Jewellers')
            ->update(['value' => 'Lax Jewellers']);

        foreach (DB::table('bank_accounts')->where('account_name', 'like', 'Lux Jewellers%')->get() as $account) {
            DB::table('bank_accounts')->where('id', $account->id)
                ->update(['account_name' => str_replace('Lux Jewellers', 'Lax Jewellers', $account->account_name)]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'store_name')->where('value', 'Lax Jewellers')
            ->update(['value' => 'Lux Jewellers']);
    }
};
