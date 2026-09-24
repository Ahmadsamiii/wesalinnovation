<?php

namespace App\Http\Controllers\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تصدير CSV يفتحه Excel بالعربية سليمة (BOM)، ومحصَّن من حقن الصيغ: خلية تبدأ
 * بـ = أو + أو - أو @ يقرؤها Excel صيغةً قد تنفّذ شيئاً، وأسماء المشاريع
 * والعملاء يكتبها المستخدمون.
 */
trait WritesCsv
{
    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    protected function csvDownload(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, escape: '');

            foreach ($rows as $row) {
                fputcsv($out, array_map(self::neutralize(...), $row), escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function neutralize(mixed $cell): mixed
    {
        return is_string($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'".$cell : $cell;
    }
}
