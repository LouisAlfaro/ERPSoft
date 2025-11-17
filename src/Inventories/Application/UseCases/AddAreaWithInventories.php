<?php
namespace Src\Inventories\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesAreaModel;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesModel;

final class AddAreaWithInventories
{
    public function __invoke(int $localId, string $areaName, array $inventories = []): void
    {
        DB::transaction(function () use ($localId, $areaName, $inventories) {
            // Crear área
            $area = InventoriesAreaModel::create([
                'name' => $areaName,
                'local_id' => $localId,
            ]);

            // Agregar inventories si existen
            foreach ($inventories as $inv) {
                InventoriesModel::create([
                    'inventories_area_id' => $area->id,
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
                ]);
            }
        });
    }
}
