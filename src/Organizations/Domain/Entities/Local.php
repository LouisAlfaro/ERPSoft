<?php
namespace Src\Organizations\Domain\Entities;

final class Local
{
    public function __construct(
        public readonly ?int $id,
        public int $companyId,
        public string $name,
    ) {}
}
