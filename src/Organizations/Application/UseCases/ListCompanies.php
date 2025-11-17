<?php
namespace Src\Organizations\Application\UseCases;

use Src\Organizations\Domain\Repositories\CompanyRepository;

final class ListCompanies
{
    public function __construct(private CompanyRepository $repo) {}

    public function __invoke(): array
    {
        return $this->repo->all();
    }
}
