<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DashboardController extends Controller
{
    /**
     * مدخل كل مستخدم بعد الدخول: تبويبه الأول المبني. المدير التنفيذي وحده
     * تبويبه الأول «نظرة عامة» على هذا المسار نفسه.
     *
     * حساب بلا دور حالة غير متوقعة (لا تسجيل ذاتي) تُعرض برسالة واضحة بدل
     * افتراض أي دور، حتى لا يُمنح وصول لم يُعتمد صراحة.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $tabs = $request->user()->roleTabs();

        if ($tabs === []) {
            return view('dashboard.no-role');
        }

        if (reset($tabs)['route'] === 'dashboard') {
            return view('dashboard.overview');
        }

        foreach ($tabs as $tab) {
            if (Route::has($tab['route'])) {
                return redirect()->route($tab['route']);
            }
        }

        return redirect()->route('sections.show', array_key_first($tabs));
    }
}
