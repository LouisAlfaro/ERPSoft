<?php
namespace Src\Audits\Domain\Enums;

enum AuditStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toArray(): array
    {
        return array_map(fn($case) => [
            'key' => $case->name,
            'value' => $case->value,
        ], self::cases());
    }
}

