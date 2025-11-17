<?php
namespace Src\Audits\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class ItemModel extends Model
{
    protected $table = 'items';


    public $timestamps = true;
    const CREATED_AT = 'creation_date';
    const UPDATED_AT = 'update_date';

    protected $fillable = [
        'category_id','name','ranking','observation','price','stock','income','other_income',
        'total_stock','physical_stock','difference','column_15'

    ];

    protected $casts = [
        'creation_date' => 'date',
        'update_date'   => 'date',
    ];

    public function category()
    {
        return $this->belongsTo(CategoryModel::class, 'category_id');
    }
}
