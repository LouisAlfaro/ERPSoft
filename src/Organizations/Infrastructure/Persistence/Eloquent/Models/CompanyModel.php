<?php
namespace Src\Organizations\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyModel extends Model
{
    protected $table = 'companies';
    protected $fillable = ['name'];

    public function locals()
    {
        return $this->hasMany(LocalModel::class, 'company_id');
    }
}
