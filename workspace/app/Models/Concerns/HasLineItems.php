<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * بنود مالية (كمية × سعر الوحدة) ومجاميعها بضريبة القيمة المضافة.
 *
 * الحساب بالهللات أعداداً صحيحة لا بكسور عشرية عائمة: ٠٫١ + ٠٫٢ لا تساوي ٠٫٣
 * بالأعداد العائمة، والفاتورة لا تحتمل هللة خطأ. التقريب نصف للأعلى، مرة
 * واحدة لكل بند ومرة للضريبة على المجموع.
 *
 * @property string $vat_rate
 * @property string $subtotal
 * @property string $vat_amount
 * @property string $total
 */
trait HasLineItems
{
    /**
     * @return HasMany<Model, $this>
     */
    abstract public function items(): HasMany;

    /**
     * يستبدل البنود كلها ويعيد حساب المجاميع.
     *
     * @param  array<int, array{description: string, quantity: string|float, unit_price: string|float}>  $items
     */
    public function syncItems(array $items): void
    {
        $this->items()->delete();

        foreach (array_values($items) as $position => $item) {
            $this->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => self::fromHalalas(self::lineTotalHalalas($item['quantity'], $item['unit_price'])),
                'position' => $position,
            ]);
        }

        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->get()->sum(fn (Model $item): int => self::toHalalas($item->total));
        $vat = intdiv($subtotal * (int) round((float) $this->vat_rate * 100) + 5000, 10000);

        $this->forceFill([
            'subtotal' => self::fromHalalas($subtotal),
            'vat_amount' => self::fromHalalas($vat),
            'total' => self::fromHalalas($subtotal + $vat),
        ])->save();
    }

    public static function toHalalas(string|int|float|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function fromHalalas(int $halalas): string
    {
        return sprintf('%s%d.%02d', $halalas < 0 ? '-' : '', intdiv(abs($halalas), 100), abs($halalas) % 100);
    }

    public static function lineTotalHalalas(string|float $quantity, string|float $unitPrice): int
    {
        return intdiv(self::toHalalas($quantity) * self::toHalalas($unitPrice) + 50, 100);
    }

    /**
     * أقصى مجموع قبل الضريبة (مئة مليار ريال): يُبقي المجاميع داخل أعمدة
     * decimal(14,2) وحساب الضريبة بالهللات داخل نطاق الأعداد الصحيحة.
     */
    public const MAX_SUBTOTAL_HALALAS = 10_000_000_000_000;

    /**
     * قواعد التحقق لمصفوفة البنود. حدّا الكمية والسعر يُبقيان إجمالي البند
     * الواحد داخل عمود decimal(14,2)؛ حد المجموع يتحقق منه withinTotalLimit.
     *
     * @return array<string, list<string>>
     */
    public static function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:99999.99', 'decimal:0,2'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
        ];
    }

    /**
     * @param  array<int, array{quantity?: mixed, unit_price?: mixed}>  $items
     */
    public static function withinTotalLimit(array $items): bool
    {
        $subtotal = 0;

        foreach ($items as $item) {
            if (! is_numeric($item['quantity'] ?? null) || ! is_numeric($item['unit_price'] ?? null)) {
                return true; // قواعد البند نفسه تتولى الخطأ
            }

            $subtotal += self::lineTotalHalalas($item['quantity'], $item['unit_price']);
        }

        return $subtotal <= self::MAX_SUBTOTAL_HALALAS;
    }
}
