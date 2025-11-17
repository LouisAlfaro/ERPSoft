<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\AuditModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\ItemModel;

class AuditsImport implements ToCollection, WithHeadingRow
{
    protected $auditId;
    protected $errors = [];
    protected $created = 0;
    protected $updated = 0;
    protected $currentCategory = null;

    public function __construct($auditId)
    {
        $this->auditId = $auditId;
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
        // Mapeo de columnas del Excel del cliente
        $categoryName = $row['categoria'] ?? null;
        $itemName = $row['item'] ?? $row['descripcion'] ?? null;
        
        // Si no hay item name, saltar esta fila
        if (!$itemName) {
            return;
        }

        // 1. Si viene categoría, actualizamos la categoría actual
        if ($categoryName) {
            $audit = AuditModel::find($this->auditId);
            if (!$audit) {
                throw new \Exception("Auditoría no encontrada");
            }

            $this->currentCategory = CategoryModel::firstOrCreate(
                [
                    'audit_id' => $audit->id,
                    'name' => trim($categoryName)
                ]
            );
        }

        // Verificar que tengamos una categoría actual
        if (!$this->currentCategory) {
            throw new \Exception("No se ha definido una categoría para este item");
        }

        // 2. Determinar el ranking según las columnas marcadas
        $ranking = null;
        
        // Buscar en las columnas de ranking
        if (isset($row['cumple']) && $this->isMarked($row['cumple'])) {
            $ranking = 2; // Verde
        } elseif (isset($row['en_proceso']) && $this->isMarked($row['en_proceso'])) {
            $ranking = 1; // Amarillo
        } elseif (isset($row['no_cumple']) && $this->isMarked($row['no_cumple'])) {
            $ranking = 0; // Rojo
        }

        // 3. Buscar si el item ya existe en esta categoría (matching por nombre)
        $item = ItemModel::where('category_id', $this->currentCategory->id)
            ->where('name', trim($itemName))
            ->first();

        // 4. Preparar datos
        $data = [
            'category_id' => $this->currentCategory->id,
            'name' => trim($itemName),
            'ranking' => $ranking,
            'observation' => $row['observaciones'] ?? null,
            'price' => 0,
            'stock' => 0,
            'income' => 0,
            'other_income' => 0,
            'total_stock' => 0,
            'physical_stock' => 0,
            'difference' => 0,
            'column_15' => 0,
        ];

        // 5. Crear o actualizar
        if ($item) {
            $item->update($data);
            $this->updated++;
        } else {
            ItemModel::create($data);
            $this->created++;
        }
    }

    protected function isMarked($value)
    {
        if (empty($value)) {
            return false;
        }
        
        // Considerar como marcado: X, x, ✓, check, 1, true, si, yes
        $marked = ['x', 'X', '✓', 'check', '1', 'true', 'si', 'yes', 'sí'];
        return in_array(strtolower(trim($value)), array_map('strtolower', $marked));
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
