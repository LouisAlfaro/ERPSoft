<?php
namespace Src\Organizations\Application\UseCases;

use Src\Organizations\Domain\Repositories\LocalRepository;

final class ListLocalsByCompany
{
    public function __construct(private LocalRepository $repo) {}

    public function __invoke(int $companyId): array
    {
        return $this->repo->byCompany($companyId);
    }
}
