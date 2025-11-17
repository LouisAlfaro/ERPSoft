<?php
namespace Src\Audits\Application\UseCases;

use Src\Audits\Domain\Entities\Audit;
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\IdentityAccess\Application\Services\PermissionChecker;
use App\Models\User;

final class OpenAudit
{
    public function __construct(
        private AuditRepository $repo,
        private PermissionChecker $perm
    ) {}

    public function __invoke(int $localId, int $supervisorId, int $createdBy, User $actor): Audit
    {
        
        $this->perm->mustBeSupervisorOrAdmin($actor);

        // Los administradores pueden crear auditorías en cualquier local
        // Los supervisores deben estar asignados al local específico
        if (!$actor->hasRole('ADMIN') && !$this->perm->userBelongsToLocal($actor->id, $localId)) {
            abort(403, 'User not assigned to this local.');
        }

       
        $audit = new Audit(
            AuditId::new(),
            $localId,
            $supervisorId,
            $createdBy,
            new \DateTimeImmutable('now')
        );

        $this->repo->save($audit);
        return $audit;
    }
}
