<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        // عدّاد الخمول في الواجهة يحوّل إلى هنا بـ ?timeout=1. إن سبقه انتهاء الجلسة
        // المخزّنة (نوم الجهاز أطول من عمرها) فلا رسالة محفوظة، فيُكتب السبب هنا.
        if ($request->boolean('timeout') && ! $request->session()->has('status')) {
            $request->session()->now('status', __('auth.timeout_idle', [
                'minutes' => trans_choice('auth.minutes', config('session.idle_timeout')),
            ]));
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
