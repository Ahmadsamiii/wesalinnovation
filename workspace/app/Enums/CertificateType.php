<?php

namespace App\Enums;

enum CertificateType: string
{
    case Completion = 'completion';
    case Participation = 'participation';

    public function label(): string
    {
        return match ($this) {
            self::Completion => 'شهادة إنجاز مشروع',
            self::Participation => 'شهادة مشاركة في مشروع',
        };
    }

    /**
     * شهادة الإنجاز للعميل صاحب المشروع، وشهادة المشاركة لأعضاء فريقه.
     */
    public function recipientRole(): string
    {
        return $this === self::Completion ? 'client' : 'team';
    }
}
