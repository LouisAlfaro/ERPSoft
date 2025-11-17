<?php
namespace Src\Organizations\Domain\Repositories;

use Src\Organizations\Domain\Entities\Company;

interface CompanyRepository
{
    public function create(Company $company): int;
    public function find(int $id): ?Company;
    /** @return Company[] */
    public function all(): array;
}
