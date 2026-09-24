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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->string('vendor_name');
            $table->string('vendor_contact')->nullable();
            $table->text('description')->nullable();
            $table->date('needed_by')->nullable();

            // المجاميع تُحسب من البنود عند كل حفظ وتُخزَّن، لأن التقارير تجمعها
            // عبر مئات الأوامر ولا يصح أن تعيد جمع كل بند في كل مرة.
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->string('status')->default('draft');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
