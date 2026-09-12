<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * ينشئ أدوار النظام السبعة من المصدر الوحيد config/roles.php — لا تُضف
     * دوراً هنا مباشرة، أضفه هناك ليبقى معرَّفاً في مكان واحد للوحة والصلاحيات معاً.
     */
    public function run(): void
    {
        foreach (array_keys(config('roles')) as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
