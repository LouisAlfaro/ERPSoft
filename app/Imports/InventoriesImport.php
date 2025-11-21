<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesAreaModel;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesModel;
use Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel;

class InventoriesImport implements ToCollection, WithHeadingRow
{
    protected $errors = [];
    protected $areasCreated = 0;
    protected $itemsAdded = 0;
    protected $localId;

    public function __construct(int $localId)
    {
        $this->localId = $localId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            // Verificar que el local existe
            $local = LocalModel::find($this->localId);
            if (!$local) {
                $this->errors[] = "Local con ID {$this->localId} no encontrado.";
                return;
            }

            // Agrupar filas por Sub area
            $groupedByArea = $rows->groupBy(function($row) {
                return trim($row['sub_area'] ?? '');
            });

            foreach ($groupedByArea as $areaName => $items) {
                if (empty($areaName)) {
                    continue; // Saltar filas sin sub area
                }

                try {
                    $this->processArea($areaName, $items);
                } catch (\Exception $e) {
                    $this->errors[] = "Error en área '{$areaName}': " . $e->getMessage();
                }
            }
        });
    }

    protected function processArea($areaName, $items)
    {
        // Buscar si el área ya existe en este local
        $area = InventoriesAreaModel::where('local_id', $this->localId)
            ->where('name', $areaName)
            ->first();

        $isNewArea = false;

        if (!$area) {
            // Crear nueva área
            $area = InventoriesAreaModel::create([
                'local_id' => $this->localId,
                'name' => $areaName,
                'creation_date' => now()->toDateString(),
                'update_date' => now()->toDateString(),
            ]);
            $isNewArea = true;
            $this->areasCreated++;
        }

        // Añadir TODOS los items al área (sin eliminar existentes, sin verificar duplicados)
        foreach ($items as $row) {
            $itemName = trim($row['descripcion'] ?? '');
            
            if (empty($itemName)) {
                continue; // Saltar filas sin descripción
            }

            // SIEMPRE crear nuevo item (incluso si ya existe uno con el mismo nombre)
            InventoriesModel::create([
                'inventories_area_id' => $area->id,
                'name' => $itemName,
                'price' => $this->parseNumber($row['precio_unitario'] ?? 0),
                'stock' => $this->parseNumber($row['stock_actual'] ?? 0),
                'income' => $this->parseNumber($row['ingresos'] ?? 0),
                'other_income' => $this->parseNumber($row['otros_ingresos'] ?? 0),
                'total_stock' => $this->parseNumber($row['total'] ?? 0),
                'physical_stock' => $this->parseNumber($row['stock_fisico'] ?? 0),
                'difference' => $this->parseNumber($row['diferencia'] ?? 0),
                'observation' => $row['observaciones'] ?? null,
                'ranking' => null,
                'creation_date' => now()->toDateString(),
                'update_date' => now()->toDateString(),
            ]);

            $this->itemsAdded++;
        }
    }

    protected function parseNumber($value)
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        // Limpiar formato de números (ej: "1,000" -> 1000)
        $cleaned = str_replace([',', ' '], '', $value);
        return is_numeric($cleaned) ? (int)$cleaned : 0;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSummary()
    {
        return [
            'areas_created' => $this->areasCreated,
            'items_added' => $this->itemsAdded,
            'errors' => count($this->errors),
        ];
    }
}
