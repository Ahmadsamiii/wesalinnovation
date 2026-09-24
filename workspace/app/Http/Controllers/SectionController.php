<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * تبويب في config/roles.php لم يُبنَ مساره بعد. يُعرض فقط لصاحب الدور، ويحوِّل
 * إلى المسار الحقيقي تلقائياً حين يُبنى.
 */
class SectionController extends Controller
{
    public function __invoke(Request $request, string $tab): View|RedirectResponse
    {
        $tabs = $request->user()->roleTabs();

        abort_unless(isset($tabs[$tab]), 404);

        if (Route::has($tabs[$tab]['route'])) {
            return redirect()->route($tabs[$tab]['route']);
        }

        return view('sections.pending', ['label' => $tabs[$tab]['label']]);
    }
}
