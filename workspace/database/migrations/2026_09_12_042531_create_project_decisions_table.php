<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            // صاحب القرار لا يُحذف حسابه وقراراته قائمة: السجل التنفيذي يفقد
            // معناه إن صار بلا منسوب إليه.
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();

            $table->string('type');
            // سبب الرفض أو الإيقاف. مطلوب سياسةً في هاتين الحالتين لا في المخطّط،
            // لأن الاعتماد والاستئناف قد يمرّان بلا تعليل.
            $table->text('note')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['project_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_decisions');
    }
};
