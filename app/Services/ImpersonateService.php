<?php

namespace App\Services;

use App\Models\User;
use App\Traits\HasDynamicLike;
use App\Traits\SortsQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class ImpersonateService
{
    use HasDynamicLike, SortsQuery;

    private const SESSION_KEY = 'impersonate_original_id';

    /**
     * Daftar user yang bisa di-impersonate: bukan diri sendiri,
     * terfilter search/role, dan diurutkan via whitelist kolom.
     */
    public function getImpersonatableUsers(
        User $currentUser,
        ?string $search = null,
        ?string $roleFilter = null,
        int $perPage = 15,
        ?string $sortField = null,
        string $sortDir = 'asc'
    ): LengthAwarePaginator {
        $query = User::with('roles')->where('id', '!=', $currentUser->id);

        if ($search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($search, $operator) {
                $q->where('name', $operator, "%{$search}%")
                    ->orWhere('email', $operator, "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->role($roleFilter);
        }

        $sortable = [
            'name' => 'users.name',
            'email' => 'users.email',
        ];

        $this->applySorting($query, $sortable, $sortField, $sortDir, 'name', 'asc', 'users.id');

        return $query->paginate($perPage);
    }

    public function start(User $target): void
    {
        if (! Auth::check() || $this->isImpersonating() || Auth::id() === $target->id) {
            abort(403, 'Tidak dapat memulai impersonate.');
        }

        Session::put(self::SESSION_KEY, Auth::id());
        Auth::loginUsingId($target->id);
    }

    public function stop(): void
    {
        $originalId = Session::get(self::SESSION_KEY);

        if ($originalId) {
            Auth::loginUsingId($originalId);
            Session::forget(self::SESSION_KEY);
        }
    }

    public function isImpersonating(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public function getOriginalUser(): ?User
    {
        $id = Session::get(self::SESSION_KEY);

        return $id ? User::find($id) : null;
    }
}
