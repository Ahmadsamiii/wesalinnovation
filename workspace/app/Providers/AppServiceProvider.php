<?php

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Contract;
use App\Models\HealthContent;
use App\Models\HiringRequest;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\ReferenceLetter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // خلف وسيط ينهي TLS يرى التطبيق الطلب http فيولّد روابط وأصولاً http
        // (محتوى مختلط ونماذج تُرسل بلا تشفير). رابط أساسي https يحسم المخطط.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // أسماء قصيرة ثابتة في أعمدة الربط المتعدد (المرفقات، سجل التدقيق،
        // أدوار Spatie) بدل أسماء الأصناف: إعادة تسمية صنف لا تكسر البيانات.
        Relation::enforceMorphMap([
            'user' => User::class,
            'project' => Project::class,
            'task' => Task::class,
            'contract' => Contract::class,
            'purchase_order' => PurchaseOrder::class,
            'invoice' => Invoice::class,
            'certificate' => Certificate::class,
            'reference_letter' => ReferenceLetter::class,
            'hiring_request' => HiringRequest::class,
            'health_content' => HealthContent::class,
        ]);

        // النظام يحوي عقوداً وبيانات مالية: عشرة أحرف بحروف وأرقام في
        // الإنتاج، وثمانية بلا شروط محلياً لتبقى الاختبارات والبذرة بسيطة.
        Password::defaults(fn (): Password => $this->app->isProduction()
            ? Password::min(10)->letters()->numbers()
            : Password::min(8));
    }
}
