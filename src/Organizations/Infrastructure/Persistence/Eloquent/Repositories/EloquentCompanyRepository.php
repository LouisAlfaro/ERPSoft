<?php
namespace Src\Organizations\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Organizations\Domain\Entities\Company;
use Src\Organizations\Domain\Repositories\CompanyRepository;
use Src\Organizations\Infrastructure\Persistence\Eloquent\Models\CompanyModel;

final class EloquentCompanyRepository implements CompanyRepository
{
    public function create(Company $company): int
    {
        $m = CompanyModel::create(['name' => $company->name]);
        return (int)$m->id;
    }

    public function find(int $id): ?Company
    {
        $m = CompanyModel::find($id);
        return $m ? new Company((int)$m->id, $m->name) : null;
    }

    public function all(): array
    {
        return CompanyModel::orderBy('name')->get()
            ->map(fn($m) => new Company((int)$m->id, $m->name))
            ->all();
    }
}
