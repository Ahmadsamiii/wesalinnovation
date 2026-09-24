<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ViewConventionsTest extends TestCase
{
    /**
     * Blade يطابق @php( السطرية حتى أول @endphp بعدها، فيبتلع ما بينهما كوداً
     * خاماً ويكسر الصفحة بخطأ لا يدل على سببه. قالب واحد بصيغة واحدة.
     */
    public function test_no_view_mixes_inline_and_block_php_directives(): void
    {
        $offenders = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file): bool => str_contains($file->getContents(), '@php(') && str_contains($file->getContents(), '@endphp'))
            ->map(fn ($file): string => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $offenders);
    }

    public function test_prose_turns_dash_lines_into_a_real_list_and_escapes_input(): void
    {
        $html = Blade::render('<x-prose :text="$text" />', ['text' => "للوقاية:\n- غيّر وضعيتك\n- افحص الجلد\n\n<script>alert(1)</script>"]);

        $this->assertStringContainsString('<ul class="list-disc space-y-1 ps-6" role="list">', $html);
        $this->assertSame(2, substr_count($html, '<li>'));
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * سطر جديد في آخر مكوّن التاريخ أو المبلغ يظهر مسافةً قبل الفاصلة أو النقطة
     * التي تليه في النص («منذ يومين ،»).
     */
    public function test_date_and_money_components_let_punctuation_follow_them_directly(): void
    {
        $html = Blade::render('<x-date :value="$date" />، <x-date :value="$date" relative />. <x-date :value="null" />، <x-money :amount="5" />.', ['date' => now()->subDays(3)]);

        $this->assertSame(2, substr_count($html, '</time>'));
        $this->assertStringContainsString('</time>،', $html);
        $this->assertStringContainsString('</time>.', $html);
        $this->assertStringContainsString(' -،', $html);
        $this->assertStringContainsString('ر.س</span>.', $html);
    }
}
