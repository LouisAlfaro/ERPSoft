<?php
namespace Src\Organizations\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class LocalModel extends Model
{
    protected $table = 'locals';
    protected $fillable = ['name', 'company_id'];

    public function company()
    {
        return $this->belongsTo(CompanyModel::class, 'company_id');
    }
}
