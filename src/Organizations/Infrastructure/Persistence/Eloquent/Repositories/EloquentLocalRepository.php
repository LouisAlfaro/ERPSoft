<?php
namespace Src\Organizations\Infrastructure\Persistence\Eloquent\Repositories;

use Src\Organizations\Domain\Entities\Local as LocalEntity;
use Src\Organizations\Domain\Repositories\LocalRepository;
use Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel;

final class EloquentLocalRepository implements LocalRepository
{
    public function create(LocalEntity $local): int
    {
        $m = LocalModel::create([
            'name'       => $local->name,
            'company_id' => $local->companyId,
        ]);
        return (int)$m->id;
    }

    public function find(int $id): ?LocalEntity
    {
        $m = LocalModel::find($id);
        return $m ? new LocalEntity((int)$m->id, (int)$m->company_id, $m->name) : null;
    }

    public function byCompany(int $companyId): array
    {
        return LocalModel::where('company_id', $companyId)->orderBy('name')->get()
            ->map(fn($m) => new LocalEntity((int)$m->id, (int)$m->company_id, $m->name))
            ->all();
    }
}
