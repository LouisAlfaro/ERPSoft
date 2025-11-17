<?php
namespace Src\IdentityAccess\Domain\Enum;

enum Role: string
{
    case ADMIN = 'ADMIN';
    case SUPERVISOR = 'SUPERVISOR';
    case AUDITOR = 'AUDITOR';

    public static function all(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}