<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'year', 'last_number'])]
class Sequence extends Model
{
    /**
     * الرقم التالي لنوع المستند في سنته الجارية: «INV-2026-0001». الصف مقفل
     * أثناء الزيادة، فطلبان متزامنان لا يأخذان الرقم نفسه.
     */
    public static function next(string $name): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($name, $year): string {
            static::query()->insertOrIgnore([
                'name' => $name,
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = static::query()->where('name', $name)->where('year', $year)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('%s-%d-%04d', $name, $year, $sequence->last_number);
        });
    }
}
