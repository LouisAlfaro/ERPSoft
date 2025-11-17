<?php
namespace Src\Audits\Domain\Entities;

use DomainException;
use Src\Audits\Domain\ValueObjects\AuditId;

final class Audit
{
    /** @var Category[] */
    private array $categories = [];

    public function __construct(
        private AuditId $id,
        private int $localId,
        private int $supervisorId,
        private int $createdBy,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $closedAt = null,
    ) {}

    public function id(): AuditId { return $this->id; }
    public function localId(): int { return $this->localId; }
    public function supervisorId(): int { return $this->supervisorId; }
    public function createdBy(): int { return $this->createdBy; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function closedAt(): ?\DateTimeImmutable { return $this->closedAt; }
    public function isClosed(): bool { return (bool)$this->closedAt; }

    public function addCategory(Category $category): void
    {
        $this->assertOpen();
        $this->categories[] = $category;
    }

    /** @return Category[] */
    public function categories(): array
    {
        return $this->categories;
    }

    public function close(): void
    {
        $this->assertOpen();
        $this->closedAt = new \DateTimeImmutable('now');
    }

    private function assertOpen(): void
    {
        if ($this->closedAt) {
            throw new DomainException('Audit already closed');
        }
    }
}
