<?php
namespace Src\Audits\Application\UseCases;

use DomainException;
use Src\Audits\Domain\Entities\Category;
use Src\Audits\Domain\Entities\Item;
use Src\Audits\Domain\Repositories\AuditRepository;
use Src\Audits\Domain\ValueObjects\AuditId;

final class AddCategoryWithItems
{
    public function __construct(private AuditRepository $repo) {}

 
    public function __invoke(string $auditUuid, string $categoryName, array $items): void
    {
        $audit = $this->repo->find(AuditId::from($auditUuid));
        if (!$audit) {
            throw new DomainException('Audit not found');
        }

        $cat = new Category(null, $categoryName);

        foreach ($items as $i) {
            $cat->addItem(new Item(
                id: null,
                name: (string)($i['name'] ?? 'Unnamed'),
                ranking: (int)($i['ranking'] ?? 0),
                observation: $i['observation'] ?? null,
                price: (int)($i['price'] ?? 0),
                stock: (int)($i['stock'] ?? 0),
                income: (int)($i['income'] ?? 0),
                otherIncome: (int)($i['other_income'] ?? 0),
                totalStock: (int)($i['total_stock'] ?? 0),
                physicalStock: (int)($i['physical_stock'] ?? 0),
                difference: (int)($i['difference'] ?? 0),
                column15: (int)($i['column_15'] ?? 0),
            ));
        }

        $audit->addCategory($cat);
        $this->repo->save($audit);
    }
}
