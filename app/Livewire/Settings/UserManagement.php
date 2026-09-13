<?php

namespace App\Livewire\Settings;

use App\Exports\UsersExport;
use App\Livewire\Traits\HasNotification;
use App\Models\Company;
use App\Models\User;
use App\Services\UserService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UserManagement extends Component
{
    use AuthorizesRequests, HasNotification, WithPagination;

    protected $paginationTheme = 'tailwind';

    public $search = '';

    public $roleFilter = '';

    public $isActiveFilter = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    public $showModal = false;

    public $editMode = false;

    // Form fields
    public $userId;

    public $name;

    public $email;

    public $password;

    public $password_confirmation;

    public $company_id;

    public $phone;

    public $position;

    public $is_active = true;

    public $selectedRoles = [];

    // Reset Password Modal
    public $showResetPasswordModal = false;

    public $resetUserId;

    public $newPassword;

    public $newPasswordConfirmation;

    // Delete Modal
    public $showDeleteModal = false;

    public $deletingUserId;

    public $deletingUserName;

    public function mount()
    {
        $this->authorize('viewAny', User::class);
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', $this->editMode ? 'unique:users,email,'.$this->userId : 'unique:users,email'],
            'company_id' => 'nullable|exists:companies,id',
            'phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'selectedRoles' => 'required|array|min:1',
            'selectedRoles.*' => 'exists:roles,name',
        ];

        if (! $this->editMode) {
            $rules['password'] = 'required|string|min:8|confirmed';
        } elseif ($this->password) {
            $rules['password'] = 'string|min:8|confirmed';
        }

        return $rules;
    }

    public function updatingSearch()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingRoleFilter()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingIsActiveFilter()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function updatingPerPage()
    {
        $this->resetPage();
        $this->filterChanged = true;
    }

    public function resetFilters()
    {
        $this->roleFilter = '';
        $this->isActiveFilter = '';
        $this->resetPage();
        $this->filterChanged = true;
        $this->notifySuccess('Filter berhasil direset.');
    }

    public function getRoleOptionsProperty(): array
    {
        return Role::all()->map(fn ($role) => [
            'value' => $role->name,
            'label' => ucfirst($role->name),
        ])->toArray();
    }

    public function getIsActiveOptionsProperty(): array
    {
        return [
            ['value' => '1', 'label' => 'Aktif'],
            ['value' => '0', 'label' => 'Nonaktif'],
        ];
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $this->authorize('update', $user);

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->company_id = $user->company_id;
        $this->phone = $user->phone;
        $this->position = $user->position;
        $this->is_active = $user->is_active;
        $this->selectedRoles = $user->getRoleNames()->toArray();

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(UserService $service)
    {
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'company_id' => $this->company_id ?: null,
                'phone' => $this->phone ?: null,
                'position' => $this->position ?: null,
                'is_active' => $this->is_active,
            ];

            if ($this->editMode) {
                $user = User::findOrFail($this->userId);
                $this->authorize('update', $user);

                $service->update($user, $data, $this->selectedRoles);
                $message = 'User berhasil diupdate!';

                if ($user->id === auth()->id()) {
                    $this->dispatch('profile-updated');
                }
            } else {
                $this->authorize('create', User::class);

                $service->create($data, $this->selectedRoles);
                $message = 'User berhasil ditambahkan!';
            }

            $this->notifySuccess($message);
            $this->closeModal();
            $this->resetForm();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function confirmDelete($id)
    {
        $user = User::findOrFail($id);
        $this->deletingUserId = $user->id;
        $this->deletingUserName = $user->name;
        $this->showDeleteModal = true;
    }

    public function delete(UserService $service)
    {
        try {
            $user = User::findOrFail($this->deletingUserId);
            $this->authorize('delete', $user);

            $service->delete($user);
            $this->notifySuccess('User berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus akun ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function toggleActive($id, UserService $service)
    {
        try {
            $user = User::findOrFail($id);
            $this->authorize('toggleActive', $user);

            $service->toggleActive($user);

            $status = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';
            $this->notifySuccess("User berhasil {$status}!");
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat mengubah status akun ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function openResetPasswordModal($id)
    {
        $this->resetUserId = $id;
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->showResetPasswordModal = true;
    }

    public function confirmResetPassword(UserService $service)
    {
        try {
            $this->validate([
                'newPassword' => 'required|string|min:8|same:newPasswordConfirmation',
                'newPasswordConfirmation' => 'required',
            ], [
                'newPassword.required' => 'Password wajib diisi',
                'newPassword.min' => 'Password minimal 8 karakter',
                'newPassword.same' => 'Password tidak cocok',
                'newPasswordConfirmation.required' => 'Konfirmasi password wajib diisi',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $user = User::findOrFail($this->resetUserId);
            $this->authorize('resetPassword', $user);

            $service->resetPassword($user, $this->newPassword);

            $this->notifySuccess('Password berhasil direset!');
            $this->closeResetPasswordModal();
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mereset password.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeResetPasswordModal()
    {
        $this->showResetPasswordModal = false;
        $this->reset(['resetUserId', 'newPassword', 'newPasswordConfirmation']);
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset([
            'userId',
            'name',
            'email',
            'password',
            'password_confirmation',
            'company_id',
            'phone',
            'position',
            'is_active',
            'selectedRoles',
        ]);

        $this->is_active = true;
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', User::class);

        return (new UsersExport($this->search, $this->roleFilter, $this->isActiveFilter !== '' ? $this->isActiveFilter : null))
            ->download('users-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf(UserService $service)
    {
        $this->authorize('exportPdf', User::class);

        $users = $service->getFilteredUsers(
            $this->search,
            $this->roleFilter,
            $this->isActiveFilter !== '' ? $this->isActiveFilter : null,
            perPage: 9999
        );

        $pdf = Pdf::loadView('exports.users-pdf', ['users' => $users]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'users-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function render(UserService $service)
    {
        $users = $service->getFilteredUsers(
            $this->search,
            $this->roleFilter,
            $this->isActiveFilter !== '' ? $this->isActiveFilter : null,
            $this->perPage
        );

        if ($this->filterChanged) {
            $this->notifySuccess("Ditemukan {$users->total()} data user.");
            $this->filterChanged = false;
        }

        return view('livewire.settings.user-management', [
            'users' => $users,
            'roles' => Role::all(),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }
}
