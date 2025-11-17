<?php
namespace Src\Audits\Domain\Repositories;

use Src\Audits\Domain\Entities\Audit;
use Src\Audits\Domain\ValueObjects\AuditId;

interface AuditRepository
{
    public function save(Audit $audit): void;
    public function find(AuditId $id): ?Audit;
}
