<?php
namespace Src\Organizations\Application\UseCases;

use Src\Organizations\Domain\Entities\Local;
use Src\Organizations\Domain\Repositories\LocalRepository;

final class CreateLocal
{
    public function __construct(private LocalRepository $repo) {}

    public function __invoke(int $companyId, string $name): int
    {
        return $this->repo->create(new Local(null, $companyId, trim($name)));
    }
}
