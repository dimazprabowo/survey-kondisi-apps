<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SsoSyncController extends Controller
{
    /**
     * Health check endpoint for SSO Server connectivity test.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'app' => config('app.name'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Sync user profile data only (name, email, is_active).
     * Roles are NOT assigned here — use /sso/users/sync-roles for that.
     * New users get default 'user' role.
     */
    public function syncUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $user = User::where('email', $validated['email'])->first();

            if ($user) {
                $user->update([
                    'name' => $validated['name'],
                    'is_active' => $validated['is_active'] ?? $user->is_active,
                ]);

                Log::info('SSO sync: user updated', ['user_id' => $user->id, 'email' => $user->email]);

                return response()->json([
                    'message' => 'User updated successfully.',
                    'user_id' => $user->id,
                    'action' => 'updated',
                ]);
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
                'is_active' => $validated['is_active'] ?? true,
            ]);

            if (method_exists($user, 'assignRole')) {
                $user->assignRole('user');
            }

            Log::info('SSO sync: user created', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'message' => 'User created successfully.',
                'user_id' => $user->id,
                'action' => 'created',
            ], 201);

        } catch (\Exception $e) {
            Log::error('SSO sync: failed', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to sync user.',
            ], 500);
        }
    }

    /**
     * Remove (deactivate) a user from SSO Server.
     */
    public function removeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'sometimes|email|max:255',
            'user_id' => 'sometimes|integer',
        ]);

        try {
            $user = null;

            if (! empty($validated['email'])) {
                $user = User::where('email', $validated['email'])->first();
            } elseif (! empty($validated['user_id'])) {
                $user = User::find($validated['user_id']);
            }

            if (! $user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            $user->update(['is_active' => false]);

            Log::info('SSO sync: user deactivated', ['user_id' => $user->id, 'email' => $user->email]);

            return response()->json([
                'message' => 'User deactivated successfully.',
                'user_id' => $user->id,
            ]);

        } catch (\Exception $e) {
            Log::error('SSO sync: remove failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to remove user.',
            ], 500);
        }
    }

    /**
     * List all users for SSO Server.
     */
    public function listUsers(): JsonResponse
    {
        $users = User::with('roles:id,name')
            ->select('id', 'name', 'email', 'is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'roles' => $user->roles->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                ])->values(),
            ]);

        return response()->json([
            'data' => $users,
        ]);
    }

    /**
     * List all roles for SSO Server.
     */
    public function listRoles(): JsonResponse
    {
        $roles = Role::with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->values(),
            ]);

        return response()->json([
            'data' => $roles,
        ]);
    }

    /**
     * List all permissions for SSO Server.
     */
    public function listPermissions(): JsonResponse
    {
        $permissions = Permission::select('id', 'name', 'guard_name')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $permissions,
        ]);
    }

    /**
     * Sync user roles from SSO Server.
     * Accepts email + array of role names.
     */
    public function syncUserRoles(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'roles' => 'required|array',
            'roles.*' => 'string',
        ]);

        try {
            $user = User::where('email', $validated['email'])->first();

            if (! $user) {
                return response()->json(['message' => 'User not found.'], 404);
            }

            $existingRoles = Role::whereIn('name', $validated['roles'])->pluck('name')->toArray();
            $user->syncRoles($existingRoles);

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            Log::info('SSO sync: user roles synced', [
                'user_id' => $user->id,
                'email' => $user->email,
                'roles' => $existingRoles,
            ]);

            return response()->json([
                'message' => 'User roles synced successfully.',
                'user_id' => $user->id,
                'roles' => $existingRoles,
            ]);

        } catch (\Exception $e) {
            Log::error('SSO sync: user roles sync failed', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to sync user roles.',
            ], 500);
        }
    }

    /**
     * Sync a role (create or update) with its permissions from SSO Server.
     */
    public function syncRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string',
        ]);

        try {
            $role = Role::firstOrCreate(
                ['name' => $validated['name'], 'guard_name' => 'web']
            );

            if (isset($validated['permissions'])) {
                $existingPerms = Permission::whereIn('name', $validated['permissions'])->pluck('name')->toArray();
                $role->syncPermissions($existingPerms);
            }

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            Log::info('SSO sync: role synced', [
                'role' => $role->name,
                'permissions' => $validated['permissions'] ?? [],
            ]);

            return response()->json([
                'message' => 'Role synced successfully.',
                'role_id' => $role->id,
                'role_name' => $role->name,
            ]);

        } catch (\Exception $e) {
            Log::error('SSO sync: role sync failed', [
                'name' => $validated['name'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to sync role.',
            ], 500);
        }
    }

    /**
     * Delete a role from SSO Server command.
     */
    public function deleteRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $role = Role::where('name', $validated['name'])->where('guard_name', 'web')->first();

            if (! $role) {
                return response()->json(['message' => 'Role not found.'], 404);
            }

            if ($role->users()->count() > 0) {
                return response()->json([
                    'message' => 'Role still has assigned users, cannot delete.',
                ], 422);
            }

            $roleName = $role->name;
            $role->delete();

            app()[PermissionRegistrar::class]->forgetCachedPermissions();

            Log::info('SSO sync: role deleted', ['role' => $roleName]);

            return response()->json([
                'message' => 'Role deleted successfully.',
                'role_name' => $roleName,
            ]);

        } catch (\Exception $e) {
            Log::error('SSO sync: role delete failed', [
                'name' => $validated['name'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete role.',
            ], 500);
        }
    }
}
