<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('health_contents', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);

            // النسخة قيد التحرير.
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->longText('body');
            // مرجع رسمي يستند إليه النص، يظهر في الصفحة العامة.
            $table->string('source_url', 500)->nullable();

            $table->string('status', 16)->default('draft');
            // يزيد مع كل تقديم للمراجعة، فيُعرف أي نسخة اعتُمدت.
            $table->unsignedInteger('version')->default(0);
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();

            // النسخة المنشورة كما اعتمدها المدير الطبي؛ تبقى منشورة أثناء
            // تحرير نسخة جديدة ومراجعتها، ولا تتغيّر إلا باعتماد جديد.
            $table->string('published_title')->nullable();
            $table->string('published_summary', 500)->nullable();
            $table->longText('published_body')->nullable();
            $table->unsignedInteger('published_version')->nullable();
            $table->timestamp('published_at')->nullable();
            // المحتوى الصحي يُراجَع دورياً حتى لو لم يتغيّر.
            $table->date('review_due_on')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_contents');
    }
};
