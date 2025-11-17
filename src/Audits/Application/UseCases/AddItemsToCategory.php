<?php
namespace Src\Audits\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\ItemModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\AuditModel;
use Src\IdentityAccess\Application\Services\PermissionChecker;
use DomainException;

final class AddItemsToCategory
{
    public function __construct(
        private AuditRepository $repo,
        private PermissionChecker $perm
    ) {}

    public function __invoke(string $auditUuid, int $categoryId, array $items, User $actor): void
    {
        $audit = $this->repo->find(AuditId::from($auditUuid));
        if (!$audit) throw new DomainException('Audit not found');

        // Los administradores pueden modificar auditorías en cualquier local
        // Los supervisores/auditores deben estar asignados al local específico
        if (!$actor->hasRole('ADMIN') && !$this->perm->userBelongsToLocal($actor->id, $audit->localId())) {
            abort(403, 'User not assigned to this local.');
        }

        // Buscar el modelo de auditoría para obtener el ID
        $auditModel = AuditModel::where('uuid', $auditUuid)->first();
        if (!$auditModel) throw new DomainException('Audit model not found');

        $cat = CategoryModel::query()
            ->where('id', $categoryId)
            ->where('audit_id', $auditModel->id)
            ->first();

        if (!$cat) throw new DomainException('Category not found in this audit');

        DB::transaction(function () use ($categoryId, $items) {
            foreach ($items as $i) {
                $itemData = [
                    'category_id'     => $categoryId,
                    'name'            => $i['name'],
                    'ranking'         => $i['ranking']         ?? 0,
                    'observation'     => $i['observation']     ?? null,
                    'price'           => $i['price']           ?? 0,
                    'stock'           => $i['stock']           ?? 0,
                    'income'          => $i['income']          ?? 0,
                    'other_income'    => $i['other_income']    ?? 0,
                    'total_stock'     => $i['total_stock']     ?? 0,
                    'physical_stock'  => $i['physical_stock']  ?? 0,
                    'difference'      => $i['difference']      ?? 0,
                    'column_15'       => $i['column_15']       ?? 0,
                ];

                if (isset($i['id']) && $i['id'] !== null) {
                    $existingItem = ItemModel::where('id', $i['id'])
                        ->where('category_id', $categoryId)
                        ->first();
                    
                    if ($existingItem) {
                        $existingItem->update($itemData);
                    } else {
                        throw new DomainException('Item with id ' . $i['id'] . ' not found in this category');
                    }
                } else {
                    ItemModel::create($itemData);
                }
            }
        });
    }
}
