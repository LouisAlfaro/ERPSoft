<?php
namespace Src\Inventories\Domain\Entities;

final class Inventory
{
    public function __construct(
        private int $id,
        private string $name,
        private ?int $ranking,
        private ?string $observation,
        private int $price,
        private int $stock,
        private int $income,
        private int $otherIncome,
        private int $totalStock,
        private int $physicalStock,
        private int $difference,
        private int $inventoriesAreaId,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function id(): int { return $this->id; }
    public function name(): string { return $this->name; }
    public function ranking(): ?int { return $this->ranking; }
    public function observation(): ?string { return $this->observation; }
    public function price(): int { return $this->price; }
    public function stock(): int { return $this->stock; }
    public function income(): int { return $this->income; }
    public function otherIncome(): int { return $this->otherIncome; }
    public function totalStock(): int { return $this->totalStock; }
    public function physicalStock(): int { return $this->physicalStock; }
    public function difference(): int { return $this->difference; }
    public function inventoriesAreaId(): int { return $this->inventoriesAreaId; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function update(array $data): void
    {
        if (isset($data['name'])) $this->name = $data['name'];
        if (isset($data['ranking'])) $this->ranking = $data['ranking'];
        if (isset($data['observation'])) $this->observation = $data['observation'];
        if (isset($data['price'])) $this->price = $data['price'];
        if (isset($data['stock'])) $this->stock = $data['stock'];
        if (isset($data['income'])) $this->income = $data['income'];
        if (isset($data['other_income'])) $this->otherIncome = $data['other_income'];
        if (isset($data['total_stock'])) $this->totalStock = $data['total_stock'];
        if (isset($data['physical_stock'])) $this->physicalStock = $data['physical_stock'];
        if (isset($data['difference'])) $this->difference = $data['difference'];
        
        $this->updatedAt = new \DateTimeImmutable('now');
    }
}
