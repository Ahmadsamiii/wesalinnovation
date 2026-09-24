<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * سؤال وجواب من مساعد المنصة العامة (جدول chat_logs في قاعدتها). للقراءة فقط:
 * مساحة العمل تراقب ولا تكتب في قاعدة المنصة. هوية السائل (user_id) لا
 * تُعرض في أي صفحة هنا.
 */
class ChatLog extends Model
{
    protected $connection = 'platform';

    protected $table = 'chat_logs';

    public $timestamps = false;

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('Platform chat logs are read-only from the workspace.');

        static::saving($refuse);
        static::deleting($refuse);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'stream' => 'boolean',
            'aborted' => 'boolean',
            'ttfb_ms' => 'integer',
            'total_ms' => 'integer',
        ];
    }

    /**
     * الربط مضبوط في البيئة (لا يعني أن القاعدة متاحة الآن).
     */
    public static function isConfigured(): bool
    {
        return filled(config('database.connections.platform.database'));
    }

    /**
     * أسئلة تحوي أياً من الكلمات (مطابقة جزئية).
     *
     * @param  Builder<ChatLog>  $query
     * @param  list<string>  $terms
     */
    public function scopeMentioningAny(Builder $query, array $terms): void
    {
        $query->where(function (Builder $query) use ($terms): void {
            foreach ($terms as $term) {
                $query->orWhere('question', 'like', '%'.addcslashes($term, '%_\\').'%');
            }
        });
    }
}
