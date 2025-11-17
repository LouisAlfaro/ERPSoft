<?php
namespace Src\Inventories\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel;

class InventoriesAreaModel extends Model
{
    protected $table = 'inventories_areas';

    public $timestamps = true;
    const CREATED_AT = 'creation_date';
    const UPDATED_AT = 'update_date';

    protected $fillable = [
        'name',
        'local_id',
    ];

    protected $casts = [
        'creation_date' => 'date',
        'update_date'   => 'date',
    ];

    public function local()
    {
        return $this->belongsTo(LocalModel::class, 'local_id');
    }

    public function inventories()
    {
        return $this->hasMany(InventoriesModel::class, 'inventories_area_id');
    }
}
