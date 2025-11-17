<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Src\IdentityAccess\Application\UseCases\RegisterUser;

class AuthController extends Controller
{
    // login por email O username
    public function login(Request $request)
    {
        $data = $request->validate([
            'login'    => ['required','string'], 
            'password' => ['required','string','min:6'],
        ]);

        $login = $data['login'];
        $pwd   = $data['password'];

        $token = auth('api')->attempt(['email' => $login, 'password' => $pwd])
              ?: auth('api')->attempt(['username' => $login, 'password' => $pwd]);

        if (!$token) {
            throw ValidationException::withMessages(['login' => ['Credenciales inválidas']]);
        }

        $user = auth('api')->user()->load(['company', 'locals', 'role']);

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'username' => $user->username,
                'role_id' => $user->role_id,
                'role' => $user->role ? $user->role->name : null,
                'company_id' => $user->company_id,
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name
                ] : null,
                'locals' => $user->locals->map(fn($local) => [
                    'id' => $local->id,
                    'name' => $local->name
                ]),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ]);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function register(Request $request, RegisterUser $useCase)
    {
        $data = $request->validate([
            'name'      => ['required','string','min:2'],
            'email'     => ['required','email','unique:users,email'],
            'username'  => ['nullable','string','min:3','max:50','unique:users,username'],
            'password'  => ['required','string','min:6'],
            // role puede venir como nombre (ADMIN|SUPERVISOR|AUDITOR) o id numérico
            'role'      => ['required'],
            'company_id' => ['required','integer','exists:companies,id'],
            // locales opcionales para asignar
            'local_ids'   => ['array'],
            'local_ids.*' => ['integer','exists:locals,id'],
        ]);

        $result = $useCase($data);

        return response()->json($result, 201);
    }

    public function refresh()
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    public function logout()
    {
        auth('api')->logout();
        return response()->json(['message' => 'Sesión cerrada']);
    }

    public function getRoles()
    {
        $roles = [
            ['id' => 1, 'name' => 'ADMIN'],
            ['id' => 2, 'name' => 'SUPERVISOR'],
            ['id' => 3, 'name' => 'AUDITOR']
        ];
        
        return response()->json($roles);
    }

    public function getUsers(Request $request)
    {
        $data = $request->validate([
            'role'      => ['nullable', 'string'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'include_password' => ['nullable'] 
        ]);

        
        $currentUser = auth()->user();
        $isAdmin = ($currentUser->role && $currentUser->role->name === 'ADMIN') || $currentUser->role === 'ADMIN';
        $includePassword = $isAdmin && ($data['include_password'] ?? false) && in_array(strtolower($data['include_password']), ['true', '1', 1, true], true);

        $query = \App\Models\User::with(['role', 'company', 'locals']);

        if (isset($data['role'])) {
            $query->where('role', strtoupper($data['role']));
        }

        if (isset($data['company_id'])) {
            $query->where('company_id', $data['company_id']);
        }

        $users = $query->orderBy('name')->get();

        $usersData = $users->map(function ($user) use ($includePassword) {
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->role ? $user->role->name : null, 
                'company_id' => $user->company_id,
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name
                ] : null,
                'locals' => $user->locals->map(fn($local) => [
                    'id' => $local->id,
                    'name' => $local->name
                ]),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ];

            if ($includePassword) {
                $userData['password_hash'] = $user->password;
            }

            return $userData;
        });

        return response()->json([
            'users' => $usersData,
            'total' => $users->count()
        ]);
    }

    public function updateUser(Request $request, $userId)
    {
        $data = $request->validate([
            'name'      => ['sometimes','string','min:2'],
            'email'     => ['sometimes','email','unique:users,email,' . $userId],
            'username'  => ['sometimes','nullable','string','min:3','max:50','unique:users,username,' . $userId],
            'password'  => ['sometimes','string','min:6'],
            'role'      => ['sometimes'],
            'company_id' => ['sometimes','integer','exists:companies,id'],
            'local_ids'   => ['sometimes','array'],
            'local_ids.*' => ['integer','exists:locals,id'],
        ]);

        $user = \App\Models\User::findOrFail($userId);
        
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        
        if (isset($data['role'])) {
            $roleModel = is_numeric($data['role'])
                ? \Src\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\RoleModel::find((int)$data['role'])
                : \Src\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\RoleModel::where('name', strtoupper(trim((string)$data['role'])))->first();
            
            if ($roleModel) {
                $data['role_id'] = $roleModel->id;
            }
            unset($data['role']);
        }

        $user->update($data);

        if (isset($data['local_ids'])) {
            $user->locals()->sync($data['local_ids']);
        }

        return response()->json($user->fresh(['company', 'locals', 'role']));
    }

    public function deleteUser($userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        $user->delete();
        
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    public function getSupervisors()
    {
        // Obtener supervisores por nombre de rol
        $supervisors = \App\Models\User::whereHas('role', function($query) {
                $query->where('name', 'SUPERVISOR');
            })
            ->with(['company', 'locals', 'role'])
            ->get();
            
        return response()->json($supervisors);
    }

    public function getSupervisor($supervisorId)
    {
        $supervisor = \App\Models\User::where('id', $supervisorId)
            ->whereHas('role', function($query) {
                $query->where('name', 'SUPERVISOR');
            })
            ->with(['company', 'locals', 'role'])
            ->firstOrFail();
            
        return response()->json($supervisor);
    }

    public function getSupervisorsByLocal($localId)
    {
        // Verificar que el local existe
        $local = \Src\Organizations\Infrastructure\Persistence\Eloquent\Models\LocalModel::find($localId);
        if (!$local) {
            return response()->json([
                'error' => 'El local especificado no existe',
                'local_id' => $localId
            ], 404);
        }

        // Obtener supervisores asignados a este local
        $supervisors = \App\Models\User::whereHas('role', function($query) {
                $query->where('name', 'SUPERVISOR');
            })
            ->whereHas('locals', function($query) use ($localId) {
                $query->where('locals.id', $localId);
            })
            ->with(['role'])
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_id' => $user->role_id,
                'role_name' => $user->role->name ?? null,
            ]);

        return response()->json([
            'local_id' => $localId,
            'local_name' => $local->name,
            'supervisors' => $supervisors
        ]);
    }

    private function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60, // segundos
        ]);
    }
}
