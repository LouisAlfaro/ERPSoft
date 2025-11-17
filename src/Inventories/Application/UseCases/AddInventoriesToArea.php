<?php
namespace Src\Inventories\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesAreaModel;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesModel;
use DomainException;

final class AddInventoriesToArea
{
    public function __invoke(int $areaId, array $inventories): void
    {
        $area = InventoriesAreaModel::find($areaId);
        if (!$area) throw new DomainException('Area not found');

        DB::transaction(function () use ($areaId, $inventories) {
            foreach ($inventories as $inv) {
                $inventoryData = [
                    'inventories_area_id' => $areaId,
                    'name'                => $inv['name'],
                    'ranking'             => $inv['ranking'] ?? null,
                    'observation'         => $inv['observation'] ?? null,
                    'price'               => $inv['price'] ?? 0,
                    'stock'               => $inv['stock'] ?? 0,
                    'income'              => $inv['income'] ?? 0,
                    'other_income'        => $inv['other_income'] ?? 0,
                    'total_stock'         => $inv['total_stock'] ?? 0,
                    'physical_stock'      => $inv['physical_stock'] ?? 0,
                    'difference'          => $inv['difference'] ?? 0,
                ];

                // Si viene con ID, actualizar; si no, crear
                if (isset($inv['id']) && $inv['id'] !== null) {
                    $existingInventory = InventoriesModel::where('id', $inv['id'])
                        ->where('inventories_area_id', $areaId)
                        ->first();
                    
                    if ($existingInventory) {
                        $existingInventory->update($inventoryData);
                    } else {
                        throw new DomainException('Inventory with id ' . $inv['id'] . ' not found in this area');
                    }
                } else {
                    InventoriesModel::create($inventoryData);
                }
            }
        });
    }
}
