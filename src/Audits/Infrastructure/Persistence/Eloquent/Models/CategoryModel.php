<?php
namespace Src\Audits\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryModel extends Model
{
    protected $table = 'categories';

    public $timestamps = true;
    const CREATED_AT = 'creation_date';
    const UPDATED_AT = 'update_date';

    protected $fillable = ['audit_id','name'];

    protected $casts = [
        'creation_date' => 'date',
        'update_date'   => 'date',
    ];

    public function audit()  { return $this->belongsTo(AuditModel::class, 'audit_id'); }
    public function items()  { return $this->hasMany(ItemModel::class, 'category_id'); }
}
