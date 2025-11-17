<?php
namespace Src\Audits\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class AuditModel extends Model
{
    protected $table = 'audits';

    public $timestamps = true;
    const CREATED_AT = 'creation_date';
    const UPDATED_AT = 'update_date';

    protected $fillable = [
        'uuid','local_id','supervisor_id','user_id','score','closed_at'
    ];

    protected $casts = [
        'creation_date' => 'date',
        'update_date'   => 'date',
        'closed_at'     => 'datetime',
    ];

    public function categories()
    {
        return $this->hasMany(CategoryModel::class, 'audit_id');
    }

    public function local()
    {
        return $this->belongsTo(\Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::class, 'local_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(\App\Models\User::class, 'supervisor_id');
    }
}
