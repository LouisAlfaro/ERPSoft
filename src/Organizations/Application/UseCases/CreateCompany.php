<?php
namespace Src\Organizations\Application\UseCases;

use Src\Organizations\Domain\Entities\Company;
use Src\Organizations\Domain\Repositories\CompanyRepository;

final class CreateCompany
{
    public function __construct(private CompanyRepository $repo) {}

    public function __invoke(string $name): int
    {
        $name = trim($name);
        return $this->repo->create(new Company(null, $name));
    }
}
