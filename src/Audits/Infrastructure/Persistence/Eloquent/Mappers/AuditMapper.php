<?php
namespace Src\Audits\Infrastructure\Persistence\Eloquent\Mappers;

use Illuminate\Support\Facades\DB;
use Src\Audits\Domain\Entities\{Audit,Category,Item};
use Src\Audits\Domain\ValueObjects\AuditId;
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\{AuditModel,CategoryModel,ItemModel};

final class AuditMapper
{
    public function persist(Audit $audit): void
    {
        DB::transaction(function () use ($audit) {
            $am = AuditModel::updateOrCreate(
                ['uuid' => (string)$audit->id()],
                [
                    'local_id'      => $audit->localId(),
                    'supervisor_id' => $audit->supervisorId(),
                    'user_id'       => $audit->createdBy(),
                    'creation_date' => $audit->createdAt()->format('Y-m-d'),
                    'closed_at'     => $audit->closedAt()?->format('Y-m-d H:i:s'),
                ]
            );

            // Limpieza simple para mantener el ejemplo claro (puedes optimizar luego)
            $am->categories()->each(function ($cm) {
                $cm->items()->delete();
                $cm->delete();
            });

            foreach ($audit->categories() as $c) {
                $cm = CategoryModel::create([
                    'audit_id'      => $am->id,
                    'name'          => $c->name,
                    'creation_date' => now()->toDateString(),
                ]);

                foreach ($c->items() as $i) {
                    ItemModel::create([
                        'category_id'     => $cm->id,
                        'name'            => $i->name,
                        'ranking'         => $i->ranking,
                        'observation'     => $i->observation,
                        'price'           => $i->price,
                        'stock'           => $i->stock,
                        'income'          => $i->income,
                        'other_income'    => $i->otherIncome,
                        'total_stock'     => $i->totalStock,
                        'physical_stock'  => $i->physicalStock,
                        'difference'      => $i->difference,
                        'column_15'       => $i->column15,
                        'creation_date'   => now()->toDateString(),
                    ]);
                }
            }
        });
    }

    public function rehydrate(AuditModel $m): Audit
    {
        $audit = new Audit(
            AuditId::from($m->uuid),
            localId: (int)$m->local_id,
            supervisorId: (int)$m->supervisor_id,
            createdBy: (int)$m->user_id,
            createdAt: new \DateTimeImmutable($m->creation_date),
            closedAt: $m->closed_at ? new \DateTimeImmutable($m->closed_at) : null
        );

        $cats = $m->categories()->with('items')->get();
        foreach ($cats as $cat) {
            $c = new Category($cat->id, $cat->name);
            foreach ($cat->items as $it) {
                $c->addItem(new Item(
                    id: $it->id,
                    name: $it->name,
                    ranking: (int)$it->ranking,
                    observation: $it->observation,
                    price: (int)$it->price,
                    stock: (int)$it->stock,
                    income: (int)$it->income,
                    otherIncome: (int)$it->other_income,
                    totalStock: (int)$it->total_stock,
                    physicalStock: (int)$it->physical_stock,
                    difference: (int)$it->difference,
                    column15: (int)$it->column_15
                ));
            }
            $audit->addCategory($c);
        }
        return $audit;
    }
}
