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
    protected $errors = [];
    protected $categoriesCreated = 0;
    protected $itemsAdded = 0;
    protected $auditId;

    public function __construct(int $auditId)
    {
        $this->auditId = $auditId;
    }

    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            // Buscar auditoría por ID
            $audit = AuditModel::where('id', $this->auditId)
                ->whereNull('closed_at')
                ->first();

            if (!$audit) {
                $this->errors[] = "No se encontró auditoría con ID {$this->auditId} o ya está cerrada.";
                return;
            }

            // Agrupar filas por categoría
            $groupedByCategory = $rows->groupBy(function($row) {
                return trim($row['categoria'] ?? '');
            });

            foreach ($groupedByCategory as $categoryName => $items) {
                if (empty($categoryName)) {
                    continue; // Saltar filas sin categoría
                }

                try {
                    $this->processCategory($audit, $categoryName, $items);
                } catch (\Exception $e) {
                    $this->errors[] = "Error en categoría '{$categoryName}': " . $e->getMessage();
                }
            }
        });
    }

    protected function processCategory($audit, $categoryName, $items)
    {
        // Buscar si la categoría ya existe en esta auditoría
        $category = CategoryModel::where('audit_id', $audit->id)
            ->where('name', $categoryName)
            ->first();

        $isNewCategory = false;

        if (!$category) {
            // Crear nueva categoría
            $category = CategoryModel::create([
                'audit_id' => $audit->id,
                'name' => $categoryName,
                'creation_date' => now()->toDateString(),
            ]);
            $isNewCategory = true;
            $this->categoriesCreated++;
        }

        // Añadir TODOS los items a la categoría (sin eliminar existentes, sin verificar duplicados)
        foreach ($items as $row) {
            $itemName = trim($row['nombre'] ?? '');
            
            if (empty($itemName)) {
                continue; // Saltar filas sin nombre de item
            }

            // Mapear puntaje de texto a número
            $ranking = $this->parseRanking($row['puntaje'] ?? '');

            // SIEMPRE crear nuevo item (incluso si ya existe uno con el mismo nombre)
            ItemModel::create([
                'category_id' => $category->id,
                'name' => $itemName,
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
                'creation_date' => now()->toDateString(),
            ]);

            $this->itemsAdded++;
        }
    }

    protected function parseRanking($value): int
    {
        $value = strtoupper(trim($value));
        
        return match($value) {
            'CUMPLE' => 2,
            'EN PROCESO', 'ENPROCESO', 'EN_PROCESO' => 1,
            'NO CUMPLE', 'NOCUMPLE', 'NO_CUMPLE' => 0,
            default => 0,
        };
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSummary()
    {
        return [
            'categories_created' => $this->categoriesCreated,
            'items_added' => $this->itemsAdded,
            'errors' => count($this->errors),
        ];
    }
}
