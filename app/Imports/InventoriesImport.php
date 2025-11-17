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
    protected $created = 0;
    protected $updated = 0;
    protected $areaId;

    public function __construct(int $areaId)
    {
        $this->areaId = $areaId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $index => $row) {
                try {
                    $this->processRow($row, $index);
                } catch (\Exception $e) {
                    $this->errors[] = "Fila " . ($index + 2) . ": " . $e->getMessage();
                }
            }
        });
    }

    protected function processRow($row, $index)
    {
        // Mapeo de columnas del Excel del cliente a nuestros campos
        $itemName = $row['descripcion'] ?? null;
        
        if (!$itemName) {
            throw new \Exception("Falta Descripción del item");
        }

        // Verificar que el área existe
        $area = InventoriesAreaModel::find($this->areaId);
        if (!$area) {
            throw new \Exception("Área de inventario no encontrada");
        }

        // Buscar si el item ya existe en esta área (matching por nombre)
        $inventory = InventoriesModel::where('inventories_area_id', $this->areaId)
            ->where('name', trim($itemName))
            ->first();

        // Preparar datos
        $data = [
            'inventories_area_id' => $this->areaId,
            'name' => trim($itemName),
            'price' => $this->parseNumber($row['precio_unitario'] ?? 0),
            'stock' => $this->parseNumber($row['stock_actual'] ?? 0),
            'income' => $this->parseNumber($row['ingresos'] ?? 0),
            'other_income' => $this->parseNumber($row['otros_ingresos'] ?? 0),
            'total_stock' => $this->parseNumber($row['total'] ?? 0),
            'physical_stock' => $this->parseNumber($row['stock_fisico'] ?? 0),
            'difference' => $this->parseNumber($row['diferencia'] ?? 0),
            'observation' => $row['observaciones'] ?? null,
            'ranking' => null, // No viene en el formato del cliente
        ];

        // Crear o actualizar
        if ($inventory) {
            $inventory->update($data);
            $this->updated++;
        } else {
            InventoriesModel::create($data);
            $this->created++;
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
            'created' => $this->created,
            'updated' => $this->updated,
            'errors' => count($this->errors),
        ];
    }
}
