<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasLineItems;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['project_id', 'contract_id', 'due_date', 'notes'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, HasLineItems;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'vat_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            $invoice->vat_rate ??= config('workspace.vat_rate');
            $invoice->client_id ??= $invoice->project?->client_id;
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<InvoicePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->latest('paid_on')->latest('id');
    }

    /**
     * التنفيذي والمالي يريان الكل، ومدير المشروع فواتير مشاريعه، والعميل
     * فواتيره المصدرة والمدفوعة فقط.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->hasAnyRole(['executive', 'finance'])) {
            return;
        }

        if ($user->hasRole('client')) {
            $query->where('client_id', $user->id)->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Paid]);

            return;
        }

        $query->whereHas('project', fn (Builder $query) => $query->where('pm_id', $user->id));
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('status', InvoiceStatus::Issued)->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Issued
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    /**
     * المتبقي على العميل بالريال (نص عشري بخانتين).
     */
    public function balance(): string
    {
        return self::fromHalalas(self::toHalalas($this->total) - self::toHalalas($this->paid_amount));
    }

    /**
     * الرقم التسلسلي يُسند هنا لا عند الإنشاء، فالمسودة المحذوفة لا تترك فجوة.
     */
    public function issue(User $by): void
    {
        throw_unless($this->status === InvoiceStatus::Draft, LogicException::class, 'Only a draft invoice can be issued.');
        throw_unless(self::toHalalas($this->total) > 0, LogicException::class, 'An invoice needs a positive total.');

        DB::transaction(function (): void {
            $this->forceFill([
                'number' => Sequence::next('INV'),
                'status' => InvoiceStatus::Issued,
                'issue_date' => today(),
                'due_date' => $this->due_date ?? today()->addDays(config('workspace.invoice_payment_terms_days')),
                'issued_at' => now(),
            ])->save();
        });

        AuditLog::record(AuditAction::InvoiceIssued, $this, actor: $by);
    }

    /**
     * الدفعة لا تتجاوز المتبقي، واكتمال السداد يغلق الفاتورة مدفوعةً.
     */
    public function recordPayment(string $amount, Carbon $paidOn, PaymentMethod $method, ?string $reference, User $by): InvoicePayment
    {
        throw_unless($this->status === InvoiceStatus::Issued, LogicException::class, 'Payments are recorded on issued invoices only.');

        $amountHalalas = self::toHalalas($amount);
        throw_unless($amountHalalas > 0 && $amountHalalas <= self::toHalalas($this->balance()), LogicException::class, 'Payment must be positive and not exceed the balance.');

        return DB::transaction(function () use ($amountHalalas, $paidOn, $method, $reference, $by): InvoicePayment {
            $payment = $this->payments()->create([
                'amount' => self::fromHalalas($amountHalalas),
                'paid_on' => $paidOn,
                'method' => $method,
                'reference' => $reference,
                'recorded_by' => $by->id,
            ]);

            $paid = self::toHalalas($this->paid_amount) + $amountHalalas;
            $isSettled = $paid >= self::toHalalas($this->total);

            $this->forceFill([
                'paid_amount' => self::fromHalalas($paid),
                'status' => $isSettled ? InvoiceStatus::Paid : InvoiceStatus::Issued,
                'paid_at' => $isSettled ? now() : null,
            ])->save();

            AuditLog::record(AuditAction::PaymentRecorded, $this, ['note' => number_format($amountHalalas / 100, 2).' ر.س ('.$method->label().')'], $by);

            return $payment;
        });
    }

    /**
     * فاتورة عليها دفعات لا تُلغى — تصحيحها إشعار دائن، لا محو.
     */
    public function cancel(User $by, string $reason): void
    {
        throw_unless($this->status === InvoiceStatus::Issued, LogicException::class, 'Only an issued invoice can be cancelled.');
        throw_if($this->payments()->exists(), LogicException::class, 'An invoice with payments cannot be cancelled.');

        $this->forceFill([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        AuditLog::record(AuditAction::InvoiceCancelled, $this, ['note' => $reason], $by);
    }

    public function auditLabel(): string
    {
        return $this->number ?? 'مسودة فاتورة #'.$this->id;
    }
}
