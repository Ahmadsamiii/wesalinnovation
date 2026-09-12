<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * يُنشئ الأدوار السبعة، ثم مستخدماً تجريبياً واحداً لكل دور لتجربة النظام
     * فوراً. هذه الحسابات التجريبية بكلمة مرور معروفة — لتطوير محلي فقط،
     * لا تُشغَّل على قاعدة إنتاج حقيقية.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        foreach (config('roles') as $role => $meta) {
            User::factory()->create([
                'name' => $meta['label'],
                'email' => $role.'@wesalinnovation.sa',
                'department' => null,
                'password' => bcrypt('password'),
            ])->assignRole($role);
        }
    }
}
