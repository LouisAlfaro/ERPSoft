<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Src\Inventories\Infrastructure\Persistence\Eloquent\Models\InventoriesModel;

class InventoriesExport implements FromCollection, WithHeadings, WithMapping
{
    protected $areaId;

    public function __construct($areaId)
    {
        $this->areaId = $areaId;
    }

    public function collection()
    {
        return InventoriesModel::query()
            ->where('inventories_area_id', $this->areaId)
            ->with(['area.local'])
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Local',
            'Sub area',
            'Descripción',
            'Precio unitario',
            'Stock actual',
            'Ingresos',
            'Otros ingresos',
            'Total',
            'Stock físico',
            'Diferencia',
            'Observaciones',
        ];
    }

    public function map($inventory): array
    {
        return [
            $inventory->area->local->name ?? '',
            $inventory->area->name ?? '',
            $inventory->name,
            $inventory->price,
            $inventory->stock,
            $inventory->income,
            $inventory->other_income,
            $inventory->total_stock,
            $inventory->physical_stock,
            $inventory->difference,
            $inventory->observation ?? '',
        ];
    }
}
