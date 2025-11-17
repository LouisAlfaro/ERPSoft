<?php
namespace Src\Audits\Application\UseCases;

use App\Models\User;
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\AuditModel;
use Src\IdentityAccess\Application\Services\PermissionChecker;
use DomainException;

final class RemoveItem
{
    public function __construct(
        private AuditRepository $repo,
        private PermissionChecker $perm
    ) {}

    public function __invoke(string $auditUuid, int $categoryId, int $itemId, User $actor): void
    {
        $audit = $this->repo->find(AuditId::from($auditUuid));
        if (!$audit) throw new DomainException('Audit not found');

        // Los administradores pueden eliminar items en cualquier local
        // Los supervisores/auditores deben estar asignados al local específico
        if (!$actor->hasRole('ADMIN') && !$this->perm->userBelongsToLocal($actor->id, $audit->localId())) {
            abort(403, 'User not assigned to this local.');
        }

        // Buscar el modelo de auditoría para obtener el ID
        $auditModel = AuditModel::where('uuid', $auditUuid)->first();
        if (!$auditModel) throw new DomainException('Audit model not found');

        $item = ItemModel::query()
            ->where('id', $itemId)
            ->where('category_id', $categoryId)
            ->whereHas('category', fn($q) => $q->where('audit_id', $auditModel->id))
            ->first();

        if (!$item) throw new DomainException('Item not found in this audit/category');

        $item->delete();
    }
}
