<?php
namespace Src\Audits\Application\UseCases;

use DomainException;
use App\Models\User;
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\IdentityAccess\Application\Services\PermissionChecker;

final class CloseAudit
{
    public function __construct(
        private AuditRepository $repo,
        private PermissionChecker $perm
    ) {}

    public function __invoke(string $auditUuid, User $actor): void
    {
        $audit = $this->repo->find(AuditId::from($auditUuid));
        if (!$audit) {
            throw new DomainException('Audit not found');
        }

       
        $this->perm->mustBeSupervisorOrAdmin($actor);

        // Los administradores pueden cerrar auditorías en cualquier local
        // Los supervisores deben estar asignados al local específico
        if (!$actor->hasRole('ADMIN') && !$this->perm->userBelongsToLocal($actor->id, $audit->localId())) {
            abort(403, 'User not assigned to this local.');
        }

        $audit->close();

        $this->repo->save($audit);
    }
}
