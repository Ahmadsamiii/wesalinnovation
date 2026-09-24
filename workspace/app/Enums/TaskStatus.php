<?php

namespace App\Enums;

/**
 * أعمدة لوحة الكانبان بهذا الترتيب نفسه.
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Blocked = 'blocked';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'قائمة الانتظار',
            self::InProgress => 'قيد التنفيذ',
            self::Review => 'مراجعة',
            self::Blocked => 'معطّلة',
            self::Done => 'منجزة',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Todo => 'gray',
            self::InProgress => 'brand',
            self::Review => 'purple',
            self::Blocked => 'red',
            self::Done => 'green',
        };
    }
}
