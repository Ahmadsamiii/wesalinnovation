<?php

namespace App\Models\Concerns;

/**
 * رمز تحقق عام يُطبع على المستند، يتحقق به أي طرف ثالث (جهة عمل، بنك، سفارة)
 * من صفحة /verify دون حساب في النظام. اثنا عشر حرفاً من أبجدية بلا حروف
 * ملتبسة (لا 0 ولا O ولا 1 ولا I): نحو ١٠^١٨ احتمالاً، فلا يُخمَّن.
 */
trait HasVerificationCode
{
    public static function newVerificationCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 12; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /**
     * «ABCD-EFGH-JKLM» للقراءة والإملاء؛ الصفحة العامة تقبله بشرطات أو بدونها.
     */
    public static function formatVerificationCode(?string $code): ?string
    {
        return $code === null ? null : implode('-', str_split($code, 4));
    }

    public static function normalizeVerificationCode(string $input): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }
}
