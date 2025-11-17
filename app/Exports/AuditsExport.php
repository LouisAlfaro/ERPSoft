<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\ItemModel;

class AuditsExport implements FromCollection, WithHeadings, WithMapping
{
    protected $auditId;

    public function __construct($auditId)
    {
        $this->auditId = $auditId;
    }

    public function collection()
    {
        return ItemModel::query()
            ->whereHas('category', function($q) {
                $q->where('audit_id', $this->auditId);
            })
            ->with(['category'])
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Nº',
            'CATEGORIA',
            'Item',
            'CUMPLE',
            'EN PROCESO',
            'NO CUMPLE',
            'OBSERVACIONES',
        ];
    }

    public function map($item): array
    {
        return [
            $item->id,
            $item->category->name ?? '',
            $item->name,
            $item->ranking === 2 ? 'X' : '',  // CUMPLE
            $item->ranking === 1 ? 'X' : '',  // EN PROCESO
            $item->ranking === 0 ? 'X' : '',  // NO CUMPLE
            $item->observation ?? '',
        ];
    }
}
