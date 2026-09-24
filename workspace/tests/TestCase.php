<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * الاختبارات تفحص سلوك الخادم لا ملفات الواجهة المجمَّعة: بدون هذا يفشل
     * كل اختبار يعرض صفحة ما لم يُشغَّل npm run build أولاً. سلامة التجميع
     * نفسه يفحصها سير CI بخطوة مستقلة.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
