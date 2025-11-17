<?php
namespace Src\Inventories\Application\UseCases;

use App\Models\User;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesAreaModel;
use DomainException;

final class RemoveArea
{
    public function __invoke(int $localId, int $areaId): void
    {
        $area = InventoriesAreaModel::where('id', $areaId)
            ->where('local_id', $localId)
            ->first();

        if (!$area) throw new DomainException('Area not found in this local');

        $area->delete();
    }
}
