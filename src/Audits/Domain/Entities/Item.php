<?php
namespace Src\Audits\Domain\Entities;

final class Item
{
    public function __construct(
        public readonly ?int $id,
        public string $name,
        public int $ranking = 0,
        public ?string $observation = null,
        public int $price = 0,
        public int $stock = 0,
        public int $income = 0,
        public int $otherIncome = 0,
        public int $totalStock = 0,
        public int $physicalStock = 0,
        public int $difference = 0,
        public int $column15 = 0,
    ) {}
}
