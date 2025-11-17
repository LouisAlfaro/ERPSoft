<?php
namespace Src\Organizations\Domain\Repositories;

use src\Organizations\Domain\Entities\Local;

interface LocalRepository
{
    public function create(Local $local): int;
    public function find(int $id): ?Local;
    /** @return Local[] */
    public function byCompany(int $companyId): array;
}
