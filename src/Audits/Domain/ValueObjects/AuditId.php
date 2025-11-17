<?php
namespace Src\Audits\Domain\ValueObjects;

use Ramsey\Uuid\Uuid;

final class AuditId
{
    private function __construct(private string $value) {}

    public static function new(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function from(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
