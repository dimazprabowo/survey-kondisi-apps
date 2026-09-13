<?php

namespace App\Livewire\Profile;

use App\Livewire\Traits\HasNotification;
use App\Models\User;
use App\Services\ImpersonateService;
use App\Traits\HasDynamicLike;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class ImpersonateUser extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public string $roleFilter = '';

    public bool $filterChanged = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function resetFilters()
    {
        $this->roleFilter = '';
        $this->resetPage();
        $this->filterChanged = true;
        $this->notifySuccess('Filter berhasil direset.');
    }

    public function startImpersonate(int $userId, ImpersonateService $service): void
    {
        abort_unless(auth()->user()->can('users_impersonate'), 403);
        abort_if($service->isImpersonating(), 403, 'Anda sedang dalam sesi impersonate.');

        $target = User::findOrFail($userId);
        $this->authorize('impersonate', $target);

        $service->start($target);

        $this->notifySuccess("Anda sekarang beraksi sebagai {$target->name}.");
        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('users_impersonate'), 403);

        $operator = $this->getLikeOperator();
        $query = User::query()
            ->when($this->search, fn ($q) => $q->where('name', $operator, "%{$this->search}%")
                ->orWhere('email', $operator, "%{$this->search}%"))
            ->when($this->roleFilter, fn ($q) => $q->role($this->roleFilter))
            ->where('id', '!=', auth()->id())
            ->orderBy('name');

        $users = $query->paginate(8);

        if ($this->filterChanged) {
            $this->notifySuccess("Ditemukan {$users->total()} data user.");
            $this->filterChanged = false;
        }

        return view('livewire.profile.impersonate-user', [
            'users' => $users,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }
}
