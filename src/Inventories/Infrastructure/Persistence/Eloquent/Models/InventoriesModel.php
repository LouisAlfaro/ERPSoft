<?php
namespace Src\Inventories\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class InventoriesModel extends Model
{
    protected $table = 'inventories';

    public $timestamps = true;
    const CREATED_AT = 'creation_date';
    const UPDATED_AT = 'update_date';

    protected $fillable = [
        'name',
        'ranking',
        'observation',
        'price',
        'stock',
        'income',
        'other_income',
        'total_stock',
        'physical_stock',
        'difference',
        'inventories_area_id',
    ];

    protected $casts = [
        'creation_date' => 'date',
        'update_date'   => 'date',
    ];

    public function area()
    {
        return $this->belongsTo(InventoriesAreaModel::class, 'inventories_area_id');
    }
}
