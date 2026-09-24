<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['title', 'description', 'milestone_id', 'priority', 'assignee_id', 'due_date'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'todo',
        'priority' => 'normal',
        'position' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<ProjectMilestone, $this>
     */
    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest('id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', TaskStatus::Done);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    /**
     * ينقل المهمة إلى عمود وموضع في لوحة الكانبان ويعيد ترقيم العمود، ويضبط
     * تواريخ البدء والإنجاز: البدء يُسجَّل أول مرة فقط، والإنجاز يُمحى إن
     * أُعيد فتح المهمة.
     */
    public function moveTo(TaskStatus $status, ?int $position = null): void
    {
        DB::transaction(function () use ($status, $position): void {
            $column = static::query()
                ->where('project_id', $this->project_id)
                ->where('status', $status)
                ->whereKeyNot($this->getKey())
                ->orderBy('position')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $position = min(max($position ?? count($column), 0), count($column));
            array_splice($column, $position, 0, [$this->getKey()]);

            if ($status !== TaskStatus::Todo && $this->started_at === null) {
                $this->started_at = now();
            }
            $this->completed_at = $status === TaskStatus::Done ? ($this->completed_at ?? now()) : null;
            $this->status = $status;
            $this->position = $position;
            $this->save();

            foreach ($column as $index => $id) {
                if ($id !== $this->getKey()) {
                    static::query()->whereKey($id)->update(['position' => $index]);
                }
            }
        });
    }

    public function auditLabel(): string
    {
        return $this->title;
    }
}
