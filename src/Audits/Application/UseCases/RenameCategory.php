<?php
namespace Src\Audits\Application\UseCases;

use App\Models\User;
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\AuditModel;
use Src\IdentityAccess\Application\Services\PermissionChecker;
use DomainException;

final class RenameCategory
{
    public function __construct(
        private AuditRepository $repo,
        private PermissionChecker $perm
    ) {}

    public function __invoke(string $auditUuid, int $categoryId, string $newName, User $actor): void
    {
        $audit = $this->repo->find(AuditId::from($auditUuid));
        if (!$audit) throw new DomainException('Audit not found');

        // Los administradores pueden renombrar categorías en cualquier local
        // Los supervisores/auditores deben estar asignados al local específico
        if (!$actor->hasRole('ADMIN') && !$this->perm->userBelongsToLocal($actor->id, $audit->localId())) {
            abort(403, 'User not assigned to this local.');
        }

        $auditModel = AuditModel::where('uuid', $auditUuid)->first();
        if (!$auditModel) throw new DomainException('Audit model not found');

        $cat = CategoryModel::where('id', $categoryId)
            ->where('audit_id', $auditModel->id)
            ->first();

        if (!$cat) throw new DomainException('Category not found in this audit');

        $cat->update(['name' => trim($newName)]);
    }
}
