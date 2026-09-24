<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * كلمة تجعل سؤال مساعد المنصة «عالي الحساسية» فيُعرض على المدير الطبي.
 */
#[Fillable(['term'])]
class SensitiveTerm extends Model
{
    /**
     * @return list<string>
     */
    public static function allTerms(): array
    {
        return static::query()->orderBy('term')->pluck('term')->all();
    }
}
