<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesAreaModel;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesModel;
use Src\Inventories\Application\UseCases\AddAreaWithInventories;
use Src\Inventories\Application\UseCases\AddInventoriesToArea;
use Src\Inventories\Application\UseCases\UpdateInventory;
use Src\Inventories\Application\UseCases\RemoveInventory;
use Src\Inventories\Application\UseCases\RenameArea;
use Src\Inventories\Application\UseCases\RemoveArea;
use App\Imports\InventoriesImport;
use App\Exports\InventoriesExport;
use Maatwebsite\Excel\Facades\Excel;

class InventoriesController extends Controller
{
    /**
     * POST /api/locals/{localId}/inventories-areas
     * Crear una nueva área de inventario (con inventories opcionales)
     */
    public function createArea(int $localId, Request $request, AddAreaWithInventories $useCase)
    {
        // Verificar que el local existe
        $localExists = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::where('id', $localId)->exists();
        if (!$localExists) {
            return response()->json(['error' => 'El local especificado no existe'], 404);
        }

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'min:2'],
                'inventories' => ['array'],
                'inventories.*.id' => ['nullable', 'integer'],
                'inventories.*.name' => ['required', 'string', 'min:1'],
                'inventories.*.ranking' => ['nullable', 'integer', 'min:0', 'max:2'],
                'inventories.*.observation' => ['nullable', 'string'],
                'inventories.*.price' => ['nullable', 'integer'],
                'inventories.*.stock' => ['nullable', 'integer'],
                'inventories.*.income' => ['nullable', 'integer'],
                'inventories.*.other_income' => ['nullable', 'integer'],
                'inventories.*.total_stock' => ['nullable', 'integer'],
                'inventories.*.physical_stock' => ['nullable', 'integer'],
                'inventories.*.difference' => ['nullable', 'integer'],
            ]);

            $useCase($localId, $data['name'], $data['inventories'] ?? []);
            
            return response()->json([
                'message' => 'Área de inventario creada correctamente'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear área de inventario',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/inventories
     * Listar todos los inventarios con filtros y paginación
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'local_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoriesModel::query()
            ->with(['area.local.company'])
            ->when($data['area_id'] ?? null, fn($q, $v) => $q->where('inventories_area_id', $v))
            ->when($data['local_id'] ?? null, fn($q, $v) => 
                $q->whereHas('area', fn($qq) => $qq->where('local_id', $v))
            )
            ->when($data['company_id'] ?? null, fn($q, $v) => 
                $q->whereHas('area.local', fn($qq) => $qq->where('company_id', $v))
            )
            ->orderBy('inventories_area_id')
            ->orderBy('name');

        $perPage = $data['per_page'] ?? 15;
        $paginated = $query->paginate($perPage);

        $inventories = $paginated->getCollection()->map(function($inventory) {
            return [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'ranking' => $inventory->ranking,
                'observation' => $inventory->observation,
                'price' => (int)$inventory->price,
                'stock' => (int)$inventory->stock,
                'income' => (int)$inventory->income,
                'other_income' => (int)$inventory->other_income,
                'total_stock' => (int)$inventory->total_stock,
                'physical_stock' => (int)$inventory->physical_stock,
                'difference' => (int)$inventory->difference,
                'area' => [
                    'id' => $inventory->area->id,
                    'name' => $inventory->area->name,
                ],
                'local' => [
                    'id' => $inventory->area->local->id,
                    'name' => $inventory->area->local->name,
                ],
                'company' => [
                    'id' => $inventory->area->local->company->id,
                    'name' => $inventory->area->local->company->name,
                ],
                'creation_date' => $inventory->creation_date,
                'update_date' => $inventory->update_date,
            ];
        });

        return response()->json([
            'data' => $inventories,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ]
        ]);
    }

    /**
     * GET /api/locals/{localId}/inventories-areas
     * Listar todas las áreas de inventario de un local
     */
    public function listAreas(int $localId)
    {
        // Verificar que el local existe
        $localExists = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::where('id', $localId)->exists();
        if (!$localExists) {
            return response()->json(['error' => 'El local especificado no existe'], 404);
        }

        $areas = InventoriesAreaModel::where('local_id', $localId)
            ->with('inventories')
            ->get();

        if ($areas->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron áreas de inventario para este local',
                'local_id' => $localId,
                'data' => []
            ]);
        }

        $areasWithContext = $areas->map(function ($area) {
            return [
                'id' => $area->id,
                'name' => $area->name,
                'local_id' => $area->local_id,
                'creation_date' => $area->creation_date,
                'update_date' => $area->update_date,
                'inventories_count' => $area->inventories->count(),
            ];
        });

        return response()->json([
            'local_id' => $localId,
            'total_areas' => $areasWithContext->count(),
            'data' => $areasWithContext,
        ]);
    }

    /**
     * PUT /api/inventories-areas/{areaId}
     * Actualizar una área de inventario
     */
    public function updateArea(int $areaId, Request $request, RenameArea $useCase)
    {
        $area = InventoriesAreaModel::findOrFail($areaId);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2'],
        ]);

        $useCase($area->local_id, $areaId, $data['name']);

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/inventories-areas/{areaId}
     * Eliminar una área de inventario
     */
    public function deleteArea(int $areaId, RemoveArea $useCase)
    {
        $area = InventoriesAreaModel::findOrFail($areaId);
        
        $useCase($area->local_id, $areaId);

        return response()->json(['message' => 'Área de inventario eliminada correctamente']);
    }

    /**
     * POST /api/inventories-areas/{areaId}/inventories
     * Agregar/Actualizar items de inventario en un área
     * Si viene con ID, actualiza; si no, crea nuevo
     */
    public function addInventories(int $areaId, Request $request, AddInventoriesToArea $useCase)
    {
        $area = InventoriesAreaModel::findOrFail($areaId);

        $data = $request->validate([
            'inventories' => ['required', 'array', 'min:1'],
            'inventories.*.id' => ['nullable', 'integer'],
            'inventories.*.name' => ['required', 'string', 'min:1'],
            'inventories.*.ranking' => ['nullable', 'integer', 'min:0', 'max:2'],
            'inventories.*.observation' => ['nullable', 'string'],
            'inventories.*.price' => ['nullable', 'integer'],
            'inventories.*.stock' => ['nullable', 'integer'],
            'inventories.*.income' => ['nullable', 'integer'],
            'inventories.*.other_income' => ['nullable', 'integer'],
            'inventories.*.total_stock' => ['nullable', 'integer'],
            'inventories.*.physical_stock' => ['nullable', 'integer'],
            'inventories.*.difference' => ['nullable', 'integer'],
        ]);

        $useCase($areaId, $data['inventories']);

        $areaUpdated = $area->fresh(['inventories']);

        return response()->json([
            'id' => $areaUpdated->id,
            'name' => $areaUpdated->name,
            'inventories' => $areaUpdated->inventories->map(fn($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'ranking' => $i->ranking,
                'observation' => $i->observation,
                'price' => (int)$i->price,
                'stock' => (int)$i->stock,
                'income' => (int)$i->income,
                'other_income' => (int)$i->other_income,
                'total_stock' => (int)$i->total_stock,
                'physical_stock' => (int)$i->physical_stock,
                'difference' => (int)$i->difference,
            ])
        ]);
    }

    /**
     * GET /api/inventories-areas/{areaId}/inventories
     * Listar todos los items de inventario en un área
     */
    public function listInventories(int $areaId)
    {
        $area = InventoriesAreaModel::with(['local.company'])->findOrFail($areaId);

        $inventories = InventoriesModel::where('inventories_area_id', $areaId)->get();

        if ($inventories->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron items en esta área de inventario',
                'area_id' => $areaId,
                'area_name' => $area->name,
                'local' => [
                    'id' => $area->local->id,
                    'name' => $area->local->name,
                    'company_id' => $area->local->company_id,
                    'company_name' => $area->local->company->name ?? null,
                ],
                'data' => []
            ]);
        }

        $inventoriesWithContext = $inventories->map(function ($inventory) use ($area) {
            return [
                'id' => $inventory->id,
                'name' => $inventory->name,
                'ranking' => $inventory->ranking,
                'observation' => $inventory->observation,
                'price' => (int)$inventory->price,
                'stock' => (int)$inventory->stock,
                'income' => (int)$inventory->income,
                'other_income' => (int)$inventory->other_income,
                'total_stock' => (int)$inventory->total_stock,
                'physical_stock' => (int)$inventory->physical_stock,
                'difference' => (int)$inventory->difference,
                'inventories_area_id' => $inventory->inventories_area_id,
                'area_name' => $area->name,
                'creation_date' => $inventory->creation_date,
                'update_date' => $inventory->update_date,
            ];
        });

        return response()->json([
            'area_id' => $areaId,
            'area_name' => $area->name,
            'local' => [
                'id' => $area->local->id,
                'name' => $area->local->name,
                'company_id' => $area->local->company_id,
                'company_name' => $area->local->company->name ?? null,
            ],
            'total_inventories' => $inventoriesWithContext->count(),
            'data' => $inventoriesWithContext,
        ]);
    }

    /**
     * PUT /api/inventories/{inventoryId}
     * Actualizar un item de inventario
     */
    public function updateInventory(int $inventoryId, Request $request, UpdateInventory $useCase)
    {
        $inventory = InventoriesModel::findOrFail($inventoryId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'min:1'],
            'ranking' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2'],
            'observation' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'nullable', 'integer'],
            'stock' => ['sometimes', 'nullable', 'integer'],
            'income' => ['sometimes', 'nullable', 'integer'],
            'other_income' => ['sometimes', 'nullable', 'integer'],
            'total_stock' => ['sometimes', 'nullable', 'integer'],
            'physical_stock' => ['sometimes', 'nullable', 'integer'],
            'difference' => ['sometimes', 'nullable', 'integer'],
        ]);

        $useCase($inventory->inventories_area_id, $inventoryId, $data);

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/inventories/{inventoryId}
     * Eliminar un item de inventario
     */
    public function deleteInventory(int $inventoryId, RemoveInventory $useCase)
    {
        $inventory = InventoriesModel::findOrFail($inventoryId);
        
        $useCase($inventory->inventories_area_id, $inventoryId);

        return response()->json(['message' => 'Item de inventario eliminado correctamente']);
    }

    /**
     * POST /api/inventories-areas/{areaId}/import
     * Importar inventories desde Excel
     */
    public function import(int $areaId, Request $request)
    {
        $area = InventoriesAreaModel::findOrFail($areaId);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv']
        ]);

        try {
            $import = new InventoriesImport($areaId);
            Excel::import($import, $request->file('file'));

            $summary = $import->getSummary();
            $errors = $import->getErrors();

            if (count($errors) > 0) {
                return response()->json([
                    'message' => 'Import completado con errores',
                    'summary' => $summary,
                    'errors' => $errors
                ], 422);
            }

            return response()->json([
                'message' => 'Import exitoso',
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/inventories-areas/{areaId}/export
     * Exportar inventories a Excel
     */
    public function export(int $areaId)
    {
        try {
            $area = InventoriesAreaModel::with('local')->findOrFail($areaId);
            
            $fileName = 'inventories_' . $area->local->name . '_' . $area->name . '_' . date('Y-m-d') . '.xlsx';

            return Excel::download(new InventoriesExport($areaId), $fileName);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al exportar',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
