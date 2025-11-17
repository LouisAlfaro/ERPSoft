<?php
namespace Src\Inventories\Domain\Entities;

final class InventoriesArea
{
    /** @var Inventory[] */
    private array $inventories = [];

    public function __construct(
        private int $id,
        private string $name,
        private int $localId,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function localId(): int { return $this->localId; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function rename(string $newName): void
    {
        $this->name = trim($newName);
        $this->updatedAt = new \DateTimeImmutable('now');
    }

    public function addInventory(Inventory $inventory): void
    {
        $this->inventories[] = $inventory;
    }

    /** @return Inventory[] */
    public function inventories(): array
    {
        return $this->inventories;
    }
}
