<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Use cases (aplicación)
use Src\Audits\Application\UseCases\OpenAudit;
use Src\Audits\Application\UseCases\AddCategoryWithItems;
use Src\Audits\Application\UseCases\CloseAudit;
use Src\IdentityAccess\Application\UseCases\RegisterUser;
use Src\Audits\Application\UseCases\AddItemsToCategory;
use Src\Audits\Application\UseCases\RenameCategory;
use Src\Audits\Application\UseCases\UpdateItem;
use Src\Audits\Application\UseCases\RemoveItem;

// Read model (para lecturas rápidas)
use Src\Audits\Infrastructure\Persistence\Eloquent\Models\AuditModel;

// Import/Export
use App\Imports\AuditsImport;
use App\Exports\AuditsExport;
use Maatwebsite\Excel\Facades\Excel;

// Enums
use Src\Audits\Domain\Enums\AuditStatus;

class AuditController extends Controller
{
    /**
     * GET /api/audits/enums
     * Obtener los enums disponibles para auditorías
     */
    public function getEnums()
    {
        return response()->json([
            'status' => AuditStatus::toArray(),
            'ranking' => [
                ['key' => 'NOT_COMPLIANT', 'value' => 0, 'label' => 'Not Compliant'],
                ['key' => 'IN_PROGRESS', 'value' => 1, 'label' => 'In Progress'],
                ['key' => 'COMPLIANT', 'value' => 2, 'label' => 'Compliant'],
            ]
        ]);
    }

    /**
     * POST /api/audits/{localId}/open
     * Crear/abrir una auditoría para un local
     * No requiere body - usa el usuario autenticado
     */
    public function open(int $localId, Request $request, OpenAudit $useCase)
    {
        $user = $request->user('api');
        $supervisorId = $user->id;

        // Crear nueva auditoría (sin restricción de auditorías abiertas)
        $auditEntity = $useCase($localId, $supervisorId, $user->id, $user);
        $audit = AuditModel::where('uuid', (string)$auditEntity->id())->first();

        return response()->json([
            'message' => 'Auditoría creada exitosamente',
            'audit' => [
                'uuid' => $audit->uuid,
                'local_id' => $audit->local_id,
                'supervisor_id' => $audit->supervisor_id,
                'supervisor_name' => $audit->supervisor->name ?? null,
                'user_id' => $audit->user_id,
                'creation_date' => $audit->creation_date?->format('Y-m-d'),
                'closed_at' => $audit->closed_at,
                'status' => AuditStatus::OPEN->value,
                'score' => (int)$audit->score,
            ]
        ], 201);
    }

    /**
     * POST /api/audits/{localId}/categories
     * Agrega una categoría (y opcionalmente items) a la auditoría.
     */
    public function addCategory(int $localId, Request $request, AddCategoryWithItems $useCase)
    {
        $data = $request->validate([
            'name'  => ['required','string','min:2'],
            'items' => ['array'],
            'items.*.id'             => ['nullable','integer'],
            'items.*.name'           => ['required','string','min:1'],
            'items.*.ranking'        => ['nullable','integer','min:0','max:2'], // 0=no cumple, 1=en proceso, 2=cumple
            'items.*.price'          => ['nullable','integer'],
            'items.*.stock'          => ['nullable','integer'],
            'items.*.income'         => ['nullable','integer'],
            'items.*.other_income'   => ['nullable','integer'],
            'items.*.total_stock'    => ['nullable','integer'],
            'items.*.physical_stock' => ['nullable','integer'],
            'items.*.difference'     => ['nullable','integer'],
            'items.*.column_15'      => ['nullable','integer'],
        ]);

        // Buscar auditoría activa para este local o crear una nueva
        $audit = $this->getOrCreateAuditForLocal($localId, $request->user('api'));

        $useCase($audit->uuid, $data['name'], $data['items'] ?? []);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/audits/{auditId}/close
     * Cierra la auditoría (ADMIN o SUPERVISOR).
     */
    public function close(int $auditId, Request $request, CloseAudit $useCase)
    {
        $audit = AuditModel::where('id', $auditId)
            ->whereNull('closed_at')
            ->firstOrFail();

        $useCase($audit->uuid, $request->user('api'));
        return response()->json(['ok' => true, 'closed' => $audit->uuid]);
    }

    /**
     * GET /api/audits
     * Listado con filtros (ADMIN/SUPERVISOR/AUDITOR).
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'local_id'      => ['nullable','integer','exists:locals,id'],
            'supervisor_id' => ['nullable','integer','exists:users,id'],
            'date_from'     => ['nullable','date'],
            'date_to'       => ['nullable','date','after_or_equal:date_from'],
            'status'        => ['nullable', Rule::in(['open','closed'])],
            'category'      => ['nullable','string'],
            'stock_min'     => ['nullable','integer'],
            'stock_max'     => ['nullable','integer'],
            'ranking'       => ['nullable','integer','min:0','max:2'],
            'difference_min' => ['nullable','integer'],
            'difference_max' => ['nullable','integer'],
            'per_page'      => ['nullable','integer','min:1','max:100'],
        ]);

        $q = AuditModel::query()
            ->with(['local.company', 'supervisor'])
            ->when($data['company_id'] ?? null, fn($qq, $v) => $qq->whereHas('local', fn($q) => $q->where('company_id', $v)))
            ->when($data['local_id'] ?? null, fn($qq, $v) => $qq->where('local_id', $v))
            ->when($data['supervisor_id'] ?? null, fn($qq, $v) => $qq->where('supervisor_id', $v))
            ->when($data['date_from'] ?? null, fn($qq, $v) => $qq->whereDate('creation_date', '>=', $v))
            ->when($data['date_to'] ?? null, fn($qq, $v) => $qq->whereDate('creation_date', '<=', $v))
            ->when(($data['status'] ?? null) === 'open', fn($qq) => $qq->whereNull('closed_at'))
            ->when(($data['status'] ?? null) === 'closed', fn($qq) => $qq->whereNotNull('closed_at'))
            ->orderByDesc('id');

        $perPage = $data['per_page'] ?? 15;
        $paginated = $q->paginate($perPage);
        
        // Formatear auditorías
        $audits = $paginated->getCollection()->map(function($audit) {
            return [
                'id' => $audit->id,
                'uuid' => $audit->uuid,
                'local_id' => $audit->local_id,
                'local_name' => $audit->local->name ?? null,
                'supervisor_id' => $audit->supervisor_id,
                'supervisor_name' => $audit->supervisor->name ?? null,
                'user_id' => $audit->user_id,
                'creation_date' => $audit->creation_date?->format('d/m/Y'),
                'closed_at' => $audit->closed_at?->format('d/m/Y H:i:s'),
                'status' => $audit->closed_at ? AuditStatus::CLOSED->value : AuditStatus::OPEN->value,
                'score' => (int)$audit->score,
            ];
        });

        // Obtener todos los locales filtrados (no solo los de las auditorías)
        $localsQuery = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::with('company');
        
        // Aplicar los mismos filtros de company y local que en auditorías
        if (isset($data['company_id'])) {
            $localsQuery->where('company_id', $data['company_id']);
        }
        if (isset($data['local_id'])) {
            $localsQuery->where('id', $data['local_id']);
        }
        
        $locals = $localsQuery->get()->map(function($local) {
            return [
                'id' => $local->id,
                'name' => $local->name,
                'company_id' => $local->company_id,
                'company_name' => $local->company->name ?? null,
            ];
        });
        
        return response()->json([
            'audits' => $audits,
            'locals' => $locals,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
                'from' => $paginated->firstItem(),
                'to' => $paginated->lastItem(),
            ]
        ]);
    }
    /**
     * GET /api/audits/detail/{auditId}
     * Detalle de auditoría por ID de auditoría (ADMIN/SUPERVISOR/AUDITOR).
     */
    public function showById(int $auditId)
    {
        $m = AuditModel::with(['categories.items', 'local'])->find($auditId);

        if (!$m) {
            return response()->json([
                'error' => 'Auditoría no encontrada',
                'audit_id' => $auditId
            ], 404);
        }

        return response()->json([
            'uuid'          => $m->uuid,
            'local_id'      => $m->local_id,
            'local_name'    => $m->local->name ?? null,
            'supervisor_id' => $m->supervisor_id,
            'user_id'       => $m->user_id,
            'creation_date' => $m->creation_date?->format('d/m/Y'),
            'closed_at'     => $m->closed_at?->format('d/m/Y H:i:s'),
            'status'        => $m->closed_at ? AuditStatus::CLOSED->value : AuditStatus::OPEN->value,
            'score'         => (int)$m->score,
            'categories'    => $m->categories->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'items'=> $c->items->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'ranking' => (int)$i->ranking,
                    'observation' => $i->observation,
                    'price' => (int)$i->price,
                    'stock' => (int)$i->stock,
                    'income' => (int)$i->income,
                    'other_income' => (int)$i->other_income,
                    'total_stock' => (int)$i->total_stock,
                    'physical_stock' => (int)$i->physical_stock,
                    'difference' => (int)$i->difference,
                    'column_15' => (int)$i->column_15,
                ]),
            ]),
        ]);
    }

    /**
     * GET /api/audits/{localId}
     * Detalle de auditoría por local_id (ADMIN/SUPERVISOR/AUDITOR).
     */
    public function show(int $localId)
    {
        // Verificar que el local existe y obtener datos
        $local = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::find($localId);
        if (!$local) {
            return response()->json([
                'error' => 'El local especificado no existe',
                'local_id' => $localId
            ], 404);
        }

        $m = AuditModel::with(['categories.items'])
            ->where('local_id', $localId)
            ->orderByDesc('id')
            ->first();

        // Si no hay auditoría para este local, devolver estructura vacía
        if (!$m) {
            return response()->json([
                'message' => 'No se encontró auditoría para este local',
                'local_id' => $localId,
                'local_name' => $local->name,
                'uuid' => null,
                'supervisor_id' => null,
                'user_id' => null,
                'creation_date' => null,
                'closed_at' => null,
                'score' => 0,
                'categories' => []
            ]);
        }

        return response()->json([
            'uuid'          => $m->uuid,
            'local_id'      => $m->local_id,
            'local_name'    => $local->name,
            'supervisor_id' => $m->supervisor_id,
            'user_id'       => $m->user_id,
            'creation_date' => $m->creation_date?->format('d/m/Y'),
            'closed_at'     => $m->closed_at?->format('d/m/Y H:i:s'),
            'status'        => $m->closed_at ? AuditStatus::CLOSED->value : AuditStatus::OPEN->value,
            'score'         => (int)$m->score,
            'categories'    => $m->categories->map(fn($c) => [
                'id'   => $c->id,
                'name' => $c->name,
                'items'=> $c->items->map(fn($i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'ranking' => (int)$i->ranking, // 0=no cumple, 1=en proceso, 2=cumple
                    'observation' => $i->observation,
                    'price' => (int)$i->price,
                    'stock' => (int)$i->stock,
                    'income' => (int)$i->income,
                    'other_income' => (int)$i->other_income,
                    'total_stock' => (int)$i->total_stock,
                    'physical_stock' => (int)$i->physical_stock,
                    'difference' => (int)$i->difference,
                    'column_15' => (int)$i->column_15,
                ]),
            ]),
        ]);
    }

    // POST /api/audits/categories/{categoryId}/items
    public function addItems(int $categoryId, Request $request, AddItemsToCategory $useCase)
    {
        $data = $request->validate([
            'items' => ['required','array','min:1'],
            'items.*.id'             => ['nullable','integer'],
            'items.*.name'           => ['required','string','min:1'],
            'items.*.ranking'        => ['nullable','integer','min:0','max:2'], // 0=no cumple, 1=en proceso, 2=cumple
            'items.*.observation'    => ['nullable','string'],
            'items.*.price'          => ['nullable','integer'],
            'items.*.stock'          => ['nullable','integer'],
            'items.*.income'         => ['nullable','integer'],
            'items.*.other_income'   => ['nullable','integer'],
            'items.*.total_stock'    => ['nullable','integer'],
            'items.*.physical_stock' => ['nullable','integer'],
            'items.*.difference'     => ['nullable','integer'],
            'items.*.column_15'      => ['nullable','integer'],
        ]);

        // Buscar la categoría y su auditoría asociada
        $category = \Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel::with('audit')->findOrFail($categoryId);
        $audit = $category->audit;

        if (!$audit) {
            return response()->json(['error' => 'No se encontró la auditoría asociada a esta categoría'], 404);
        }

        $useCase($audit->uuid, $categoryId, $data['items'], $request->user('api'));

        // Obtener la categoría actualizada con sus items
        $categoryUpdated = $audit->categories()->with('items')->findOrFail($categoryId);

        return response()->json([
            'id' => $categoryUpdated->id,
            'name' => $categoryUpdated->name,
            'items' => $categoryUpdated->items->map(fn($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'ranking' => $i->ranking,
                'observation' => $i->observation,
                'price' => (int)$i->price,
                'stock' => (int)$i->stock,
                'income' => (int)$i->income,
                'other_income' => (int)$i->other_income,
                'total_stock' => (int)$i->total_stock,
                'physical_stock' => (int)$i->physical_stock,
                'difference' => (int)$i->difference,
                'column_15' => (int)$i->column_15,
            ])
        ]);
    
    
    }
    /**
     * GET /api/items
     * Listar items con filtros opcionales (company_id, category_id)
     */
    public function listItems(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer']
        ]);

        // Verificar que company_id existe si se proporciona
        if (isset($data['company_id'])) {
            $companyExists = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\CompanyModel::where('id', $data['company_id'])->exists();
            if (!$companyExists) {
                return response()->json([
                    'error' => 'La compañía especificada no existe',
                    'company_id' => $data['company_id']
                ], 404);
            }
        }

        // Verificar que category_id existe si se proporciona
        if (isset($data['category_id'])) {
            $categoryExists = \Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel::where('id', $data['category_id'])->exists();
            if (!$categoryExists) {
                return response()->json([
                    'error' => 'La categoría especificada no existe',
                    'category_id' => $data['category_id']
                ], 404);
            }
        }

        $query = \Src\Audits\Infrastructure\Persistence\Eloquent\Models\ItemModel::query()
            ->with(['category.audit.local']);

        if (isset($data['category_id'])) {
            $query->where('category_id', $data['category_id']);
        }

        if (isset($data['company_id'])) {
            try {
                $query->whereHas('category.audit.local', function($q) use ($data) {
                    $q->where('company_id', $data['company_id']);
                });
            } catch (\Exception $e) {
                return response()->json(['error' => 'Error en filtro company: ' . $e->getMessage()], 500);
            }
        }

        $items = $query->orderBy('name')->get();

        // Si no hay items, devolver mensaje
        if ($items->isEmpty()) {
            $response = [
                'message' => 'No se encontraron items con los filtros especificados',
                'data' => []
            ];
            
            // Si se filtró por company, agregar lista de locales de esa compañía
            if (isset($data['company_id'])) {
                $locales = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::where('company_id', $data['company_id'])
                    ->select('id', 'name')
                    ->get();
                $response['company_locals'] = $locales;
            }
            
            return response()->json($response);
        }

        // Mapear items con información adicional
        $itemsWithContext = $items->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'ranking' => $item->ranking,
                'observation' => $item->observation,
                'price' => (int)$item->price,
                'stock' => (int)$item->stock,
                'income' => (int)$item->income,
                'other_income' => (int)$item->other_income,
                'total_stock' => (int)$item->total_stock,
                'physical_stock' => (int)$item->physical_stock,
                'difference' => (int)$item->difference,
                'column_15' => (int)$item->column_15,
                'category_id' => $item->category_id,
                'category_name' => $item->category->name ?? null,
                'local_id' => $item->category->audit->local_id ?? null,
                'local_name' => $item->category->audit->local->name ?? null,
                'creation_date' => $item->creation_date,
                'update_date' => $item->update_date
            ];
        });

        $response = [
            'data' => $itemsWithContext
        ];

        // Si se filtró por company, agregar lista de locales de esa compañía
        if (isset($data['company_id'])) {
            $locales = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::where('company_id', $data['company_id'])
                ->select('id', 'name')
                ->get();
            $response['company_locals'] = $locales;
        }

        return response()->json($response);
    }

    // PUT /api/audits/{localId}/categories/{categoryId}
    public function renameCategory(int $localId, int $categoryId, Request $request, RenameCategory $useCase)
    {
        $data = $request->validate(['name' => ['required','string','min:2']]);
        
        $audit = AuditModel::where('local_id', $localId)
            ->whereNull('closed_at')
            ->firstOrFail();
            
        $useCase($audit->uuid, $categoryId, $data['name'], $request->user('api'));
        return response()->json(['ok' => true]);
    }

    // PUT /api/audits/{localId}/categories/{categoryId}/items/{itemId}
    public function updateItem(int $localId, int $categoryId, int $itemId, Request $request, UpdateItem $useCase)
    {
        $data = $request->validate([
            'name'            => ['sometimes','string','min:1'],
            'ranking'         => ['sometimes','nullable','integer','min:0','max:2'], // 0=no cumple, 1=en proceso, 2=cumple
            'observation'     => ['sometimes','nullable','string'],
            'price'           => ['sometimes','nullable','integer'],
            'stock'           => ['sometimes','nullable','integer'],
            'income'          => ['sometimes','nullable','integer'],
            'other_income'    => ['sometimes','nullable','integer'],
            'total_stock'     => ['sometimes','nullable','integer'],
            'physical_stock'  => ['sometimes','nullable','integer'],
            'difference'      => ['sometimes','nullable','integer'],
            'column_15'       => ['sometimes','nullable','integer'],
        ]);

        $audit = AuditModel::where('local_id', $localId)
            ->whereNull('closed_at')
            ->firstOrFail();

        $useCase($audit->uuid, $categoryId, $itemId, $data, $request->user('api'));
        return response()->json(['ok' => true]);
    }

    // DELETE /api/audits/{localId}/categories/{categoryId}/items/{itemId}
    public function removeItem(int $localId, int $categoryId, int $itemId, Request $request, RemoveItem $useCase)
    {
        $audit = AuditModel::where('local_id', $localId)
            ->whereNull('closed_at')
            ->firstOrFail();
            
        $useCase($audit->uuid, $categoryId, $itemId, $request->user('api'));
        return response()->json(['ok' => true]);
    }

    // DELETE /api/audits/categories/{categoryId}
    public function removeCategory(int $categoryId, Request $request)
    {
        try {
            // Verificar que la categoría existe
            $category = \Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel::findOrFail($categoryId);

            // Verificar que la auditoría está abierta
            $audit = AuditModel::where('id', $category->audit_id)
                ->whereNull('closed_at')
                ->firstOrFail();

            // Eliminar la categoría (esto también eliminará los items por cascade si está configurado)
            $category->delete();

            return response()->json([
                'message' => 'Categoría eliminada correctamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al eliminar categoría',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método helper para obtener o crear una auditoría para un local
     */
    private function getOrCreateAuditForLocal(int $localId, $user)
    {
        // Buscar auditoría abierta para este local
        $audit = AuditModel::where('local_id', $localId)
            ->whereNull('closed_at')
            ->first();

        if (!$audit) {
            // Crear nueva auditoría
            $openAudit = app(OpenAudit::class);
            $auditEntity = $openAudit($localId, $user->id, $user->id, $user);
            $audit = AuditModel::where('uuid', (string)$auditEntity->id())->first();
        }

        return $audit;
    }
    /**
     * GET /api/categories/{categoryId}/items
     * Devuelve solo los items de una categoría específica
     */
    public function getCategoryItems($categoryId)
    {
        // Verificar que la categoría existe
        $categoryExists = \Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel::where('id', $categoryId)->exists();
        if (!$categoryExists) {
            return response()->json([
                'error' => 'La categoría especificada no existe',
                'category_id' => $categoryId
            ], 404);
        }

        $category = \Src\Audits\Infrastructure\Persistence\Eloquent\Models\CategoryModel::with(['items', 'audit.local'])->findOrFail($categoryId);
        
        // Si no hay items en la categoría
        if ($category->items->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron items en esta categoría',
                'category_id' => $categoryId,
                'category_name' => $category->name,
                'local_id' => $category->audit->local_id ?? null,
                'local_name' => $category->audit->local->name ?? null,
                'data' => []
            ]);
        }

        // Mapear items con información adicional
        $itemsWithContext = $category->items->map(function($item) use ($category) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'ranking' => $item->ranking,
                'observation' => $item->observation,
                'price' => (int)$item->price,
                'stock' => (int)$item->stock,
                'income' => (int)$item->income,
                'other_income' => (int)$item->other_income,
                'total_stock' => (int)$item->total_stock,
                'physical_stock' => (int)$item->physical_stock,
                'difference' => (int)$item->difference,
                'column_15' => (int)$item->column_15,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'local_id' => $category->audit->local_id ?? null,
                'local_name' => $category->audit->local->name ?? null,
                'creation_date' => $item->creation_date,
                'update_date' => $item->update_date
            ];
        });
        
        return response()->json([
            'category_id' => $category->id,
            'category_name' => $category->name,
            'local_id' => $category->audit->local_id ?? null,
            'local_name' => $category->audit->local->name ?? null,
            'data' => $itemsWithContext
        ]);
    }

    /**
     * GET /api/locals/{localId}/categories
     * Listar todas las categorías de un local específico
     */
    public function getLocalCategories($localId)
    {
        // Verificar que el local existe
        $localExists = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::where('id', $localId)->exists();
        if (!$localExists) {
            return response()->json([
                'error' => 'El local especificado no existe',
                'local_id' => $localId
            ], 404);
        }

        // Obtener información del local
        $local = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::with('company')->find($localId);

        // Buscar auditoría activa para este local
        $audit = AuditModel::with(['categories'])
            ->where('local_id', $localId)
            ->orderByDesc('id')
            ->first();

        if (!$audit) {
            return response()->json([
                'message' => 'No se encontró auditoría para este local',
                'local_id' => $localId,
                'local_name' => $local->name,
                'company_id' => $local->company_id,
                'company_name' => $local->company->name ?? null,
                'data' => []
            ]);
        }

        // Si no hay categorías
        if ($audit->categories->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron categorías para este local',
                'local_id' => $localId,
                'local_name' => $local->name,
                'company_id' => $local->company_id,
                'company_name' => $local->company->name ?? null,
                'audit_id' => $audit->id,
                'data' => []
            ]);
        }

        // Mapear categorías con información del local
        $categoriesWithContext = $audit->categories->map(function($category) use ($local, $audit) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'audit_id' => $audit->id,
                'local_id' => $local->id,
                'local_name' => $local->name,
                'company_id' => $local->company_id,
                'company_name' => $local->company->name ?? null,
                'creation_date' => $category->creation_date,
                'update_date' => $category->update_date
            ];
        });

        return response()->json([
            'local_id' => $localId,
            'local_name' => $local->name,
            'company_id' => $local->company_id,
            'company_name' => $local->company->name ?? null,
            'audit_id' => $audit->id,
            'total_categories' => $categoriesWithContext->count(),
            'data' => $categoriesWithContext
        ]);
    }

    /**
     * POST /api/audits/{localId}/import
     * Importar auditoría desde Excel
     */
    public function import(int $localId, Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv']
        ]);

        try {
            // Buscar o crear auditoría para este local
            $audit = $this->getOrCreateAuditForLocal($localId, $request->user('api'));

            $import = new AuditsImport($audit->id);
            Excel::import($import, $request->file('file'));

            $summary = $import->getSummary();
            $errors = $import->getErrors();

            if (count($errors) > 0) {
                return response()->json([
                    'message' => 'Import completado con errores',
                    'summary' => $summary,
                    'errors' => $errors
                ], 422);
            }

            return response()->json([
                'message' => 'Import exitoso',
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al procesar el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/audits/{localId}/export
     * Exportar auditoría a Excel
     */
    public function export(int $localId)
    {
        try {
            $audit = AuditModel::where('local_id', $localId)
                ->orderByDesc('id')
                ->first();

            if (!$audit) {
                return response()->json([
                    'error' => 'No se encontró auditoría para este local'
                ], 404);
            }

            $local = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::find($localId);
            $fileName = 'auditoria_' . $local->name . '_' . date('Y-m-d') . '.xlsx';

            return Excel::download(new AuditsExport($audit->id), $fileName);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al exportar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
