<?php

namespace App\Enums;

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
}
