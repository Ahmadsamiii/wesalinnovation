<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * لوحة تحكم واحدة تعرض تبويبات دور المستخدم — مصدرها الوحيد
     * config/roles.php، فلا تُذكر الأدوار أو تبويباتها في أي مكان آخر.
     * كل حساب يُنشئه مدير النظام بدور واحد محدَّد؛ حساب بلا دور حالة غير
     * متوقعة (لا مسار تسجيل ذاتي في هذا النظام) نعرضها برسالة واضحة بدل
     * افتراض أي دور، حتى لا يُمنح وصول لم يُعتمد صراحة.
     */
    public function index(Request $request): View
    {
        $roleName = $request->user()->roles->first()?->name;
        $role = $roleName ? config("roles.{$roleName}") : null;

        return view('dashboard', [
            'roleName' => $roleName,
            'role' => $role,
        ]);
    }
}
