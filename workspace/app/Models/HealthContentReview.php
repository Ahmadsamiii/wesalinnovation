<?php

namespace App\Models;

use App\Enums\ApprovalDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قرار طبي على نسخة بعينها من محتوى صحي، ومعه نص ما رُوجع كما كان.
 */
#[Fillable(['reviewer_id', 'decision', 'note', 'version', 'reviewed_title', 'reviewed_body', 'submitted_at'])]
class HealthContentReview extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ApprovalDecision::class,
            'version' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<HealthContent, $this>
     */
    public function content(): BelongsTo
    {
        return $this->belongsTo(HealthContent::class, 'health_content_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
