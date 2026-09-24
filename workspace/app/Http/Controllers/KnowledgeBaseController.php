<?php

namespace App\Http\Controllers;

use App\Models\HealthContent;
use Illuminate\View\View;

/**
 * الصفحة العامة للمحتوى الصحي المعتمد: ما يستشهد به مساعد المنصة حين يجيب
 * منه. النسخة المعتمدة وحدها، بلا دخول.
 */
class KnowledgeBaseController extends Controller
{
    public function __invoke(HealthContent $content): View
    {
        abort_unless($content->isPublished(), 404);

        return view('kb.show', ['content' => $content]);
    }
}
