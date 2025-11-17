<?php
namespace Src\Inventories\Application\UseCases;

use App\Models\User;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesModel;
use DomainException;

final class UpdateInventory
{
    public function __invoke(int $areaId, int $inventoryId, array $data): void
    {
        $inventory = InventoriesModel::query()
            ->where('id', $inventoryId)
            ->where('inventories_area_id', $areaId)
            ->first();

        if (!$inventory) throw new DomainException('Inventory not found in this area');

        $inventory->update($data);
    }
}
