<?php
namespace Src\Audits\Domain\Entities;

final class Category
{
    /** @var Item[] */
    private array $items = [];

    public function __construct(
        public readonly ?int $id,
        public string $name
    ) {}

    public function addItem(Item $item): void
    {
        $this->items[] = $item;
    }

    /** @return Item[] */
    public function items(): array
    {
        return $this->items;
    }
}
