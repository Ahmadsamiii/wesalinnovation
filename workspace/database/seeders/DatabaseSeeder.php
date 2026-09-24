<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * يُنشئ الأدوار السبعة، ثم مستخدماً تجريبياً واحداً لكل دور لتجربة النظام
     * فوراً. هذه الحسابات التجريبية بكلمة مرور معروفة — لتطوير محلي فقط،
     * لا تُشغَّل على قاعدة إنتاج حقيقية.
     *
     * أحداث النماذج مفعّلة عمداً (بلا WithoutModelEvents): أرقام العقود وأوامر
     * الشراء تُسند عند الإنشاء، والبيانات التجريبية تمر بمسارات النظام الفعلية.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        foreach (config('roles') as $role => $meta) {
            User::factory()->role($role)->create([
                'name' => $meta['label'],
                'email' => $role.'@wesalinnovation.sa',
                'department' => null,
                'job_title' => $meta['label'],
                'joined_at' => now()->subYear()->startOfMonth(),
                'password' => bcrypt('password'),
            ]);
        }

        $this->call(DemoProjectSeeder::class);
    }
}
