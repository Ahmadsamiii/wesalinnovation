<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stage', 'decision', 'note', 'decided_by', 'decided_at'])]
class PurchaseOrderApproval extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ApprovalStage::class,
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
