<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * الأدوار السبعة بيانات أساسية لا بيانات تجريبية: النظام لا يعمل بدونها،
     * فتُنشأ مع الترحيل نفسه بدل الاعتماد على تذكّر تشغيل بذرة على الخادم.
     * أي دور يُضاف لاحقاً في config/roles.php يُنشأ عند أول إسناد
     * (User::assignSingleRole) أو بتشغيل RoleSeeder.
     */
    public function up(): void
    {
        foreach (array_keys(config('roles')) as $name) {
            Role::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * لا تراجع: حذف الأدوار يجرّد كل الحسابات من صلاحياتها.
     */
    public function down(): void
    {
        //
    }
};
