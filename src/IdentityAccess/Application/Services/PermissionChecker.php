<?php
namespace Src\IdentityAccess\Application\Services;

use Illuminate\Support\Facades\DB;
use App\Models\User;
use Src\IdentityAccess\Domain\Enum\Role;

final class PermissionChecker
{
    public function userBelongsToLocal(int $userId, int $localId): bool
    {
        return DB::table('user_local')
            ->where('user_id', $userId)
            ->where('local_id', $localId)
            ->exists();
    }

    public function mustBeSupervisorOrAdmin(User $user): void
    {
        
        if (!$user->inAnyRole([Role::ADMIN->value, Role::SUPERVISOR->value])) {
            abort(403, 'Only supervisors or admins can perform this action.');
        }
    }
}
