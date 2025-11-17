<?php
namespace Src\Organizations\Domain\Entities;

final class Company
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
    ) {}
}
