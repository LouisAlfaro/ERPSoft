<?php
namespace Src\IdentityAccess\Application\UseCases;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Src\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use InvalidArgumentException;

final class RegisterUser
{

    public function __invoke(array $input): array
    {
        return DB::transaction(function () use ($input) {

            $roleId = $this->resolveRoleId($input['role']);

            $user = User::create([
                'name'       => $input['name'],
                'email'      => $input['email'],
                'username'   => $input['username'] ?? null,
                'password'   => Hash::make($input['password']),
                'role_id'    => $roleId,
                'company_id' => $input['company_id'] ?? null,
            ]);

            // asignar locales (opcional)
            if (!empty($input['local_ids'])) {
                $rows = array_map(fn ($localId) => [
                    'user_id'    => $user->id,
                    'local_id'   => (int)$localId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $input['local_ids']);

                DB::table('user_local')->insert($rows);
            }

            // si quieres devolver token de una vez:
            $token = auth('api')->login($user);

            return [
                'user' => [
                    'id'       => $user->id,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'username' => $user->username,
                    'role'     => $user->role?->name,
                ],
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => auth('api')->factory()->getTTL() * 60,
            ];
        });
    }

    private function resolveRoleId(string|int $role): int
    {
        $model = is_numeric($role)
            ? RoleModel::find((int)$role)
            : RoleModel::where('name', strtoupper(trim((string)$role)))->first();

        if (!$model) {
            throw new InvalidArgumentException('Invalid role.');
        }
        return (int)$model->id;
    }
}
