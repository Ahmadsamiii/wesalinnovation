<?php

namespace App\Models;

use App\Enums\AlertOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مراجعة المدير الطبي لسؤال حساس من سجل المنصة (بمعرّفه هناك).
 */
#[Fillable(['chat_log_id', 'outcome', 'note', 'reviewed_by', 'reviewed_at'])]
class QuestionAlertReview extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => AlertOutcome::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
