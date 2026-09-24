<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cheque = 'cheque';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'تحويل بنكي',
            self::Card => 'بطاقة',
            self::Cheque => 'شيك',
            self::Cash => 'نقداً',
        };
    }
}
