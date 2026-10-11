<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Safe to run again: modules are matched by ModuleKey (added or renamed, never duplicated),
 * and default access is only added where a user type has no row yet for that module.
 *
 * Run:  php artisan db:seed --class=ModuleSeeder
 */
class ModuleSeeder extends Seeder
{
    /** ModuleKey => [ModuleName, SortOrder]. ModuleKey is what the code uses: do not rename keys later. */
    private const MODULES = [
        'dashboard'     => ['Dashboard (Main Overview)',  10],
        'agent-sales'   => ['Agent Sales Dashboard',      20],
        'sales-summary' => ['Sales Summary',              30],
        'week-deposit'  => ['Week Deposit Monitoring',    40],
        'agents-list'   => ['Agents List / Agent Status', 50],
        'agents-oic'    => ['Agents With OIC',            60],
        'users'         => ['User Accounts',              70],
        'user-types'    => ['User Types',                 80],
        'module-access' => ['Module Access',              90],
        'audit-logs'    => ['Audit Trail Logs',          100],
    ];

    /** Non-admin user types get View on these, so today's behaviour is kept. Admin modules stay closed. */
    private const DEFAULT_VIEW = ['dashboard', 'agent-sales', 'sales-summary', 'week-deposit', 'agents-list', 'agents-oic'];

    public function run(): void
    {
        // 1) the modules
        foreach (self::MODULES as $key => [$name, $order]) {
            DB::table('IWMS_Modules')->updateOrInsert(
                ['ModuleKey' => $key],
                ['ModuleName' => $name, 'SortOrder' => $order]
            );
        }
        $this->command->info('Modules: ' . count(self::MODULES) . ' added or updated.');

        // 2) default access for non-admin user types
        $adminIds = config('iwats.admin_type_ids', []);
        if (! $adminIds) {
            $this->command->warn('ADMIN_USERTYPE_IDS is empty, so default access was NOT added. Set it in .env, run php artisan config:clear, then run this seeder again.');
            return;
        }

        $moduleIds = DB::table('IWMS_Modules')->whereIn('ModuleKey', self::DEFAULT_VIEW)->pluck('ID');
        $typeIds   = DB::table('IWMS_UserTypes')->whereNotIn('ID', $adminIds)->pluck('ID');

        $added = 0;
        foreach ($typeIds as $typeId) {
            foreach ($moduleIds as $moduleId) {
                $exists = DB::table('IWMS_UserTypeAccess')
                    ->where('UserTypeID', $typeId)->where('ModuleID', $moduleId)->exists();

                if (! $exists) {
                    DB::table('IWMS_UserTypeAccess')->insert([
                        'UserTypeID' => $typeId, 'ModuleID' => $moduleId,
                        'CanView' => 1, 'CanCreate' => 0, 'CanEdit' => 0, 'CanDelete' => 0,
                    ]);
                    $added++;
                }
            }
            Cache::forget("access:type:{$typeId}");
        }
        Cache::forget('dd:modules');

        $this->command->info("Access: {$added} default View row(s) added for non-admin user types (admin types need none).");
    }
}