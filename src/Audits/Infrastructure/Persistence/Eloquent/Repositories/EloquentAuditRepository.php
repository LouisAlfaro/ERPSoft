<?php
namespace Src\Audits\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\Entities\Audit;
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\AuditModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Mappers\AuditMapper;

final class EloquentAuditRepository implements AuditRepository
{
    public function __construct(private AuditMapper $mapper) {}

    public function save(Audit $audit): void
    {
        $this->mapper->persist($audit);
    }

    public function find(AuditId $id): ?Audit
    {
        $m = AuditModel::where('uuid', (string)$id)->first();
        return $m ? $this->mapper->rehydrate($m) : null;
    }
}
