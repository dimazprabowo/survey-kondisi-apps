<?php

namespace App\Livewire\Settings;

use App\Exports\RolesExport;
use App\Livewire\Traits\HasNotification;
use App\Services\RolePermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Spatie\Permission\Models\Role;

class RolePermission extends Component
{
    use AuthorizesRequests, HasNotification;

    public $roles;

    public $permissions;

    public $selectedRole;

    public $rolePermissions = [];

    public $showModal = false;

    public $editMode = false;

    // Form fields
    public $roleId;

    public $roleName;

    public $selectedPermissions = [];

    // Permission groups for better organization
    public $permissionGroups = [];

    // Delete Modal
    public $showDeleteModal = false;

    public $deletingRoleId;

    public $deletingRoleName;

    public function mount(RolePermissionService $service)
    {
        $this->authorize('viewAny', Role::class);
        $this->permissionGroups = $service->buildPermissionGroups();
        $this->loadData($service);
    }

    public function loadData(?RolePermissionService $service = null)
    {
        $service ??= app(RolePermissionService::class);

        $this->roles = $service->getAllRolesWithPermissions();
        $this->permissions = $service->getAllPermissions();

        if ($this->selectedRole) {
            $this->rolePermissions = $service->getRolePermissions($this->selectedRole);
        }
    }

    public function selectRole($roleId, RolePermissionService $service)
    {
        $this->selectedRole = $roleId;
        $this->loadData($service);
    }

    public function togglePermission($permission, RolePermissionService $service)
    {
        if (! $this->selectedRole) {
            $this->notifyError('Pilih role terlebih dahulu!');

            return;
        }

        try {
            $role = Role::findOrFail($this->selectedRole);
            $this->authorize('togglePermission', $role);

            $result = $service->togglePermission($role, $permission);
            $message = $result === 'granted' ? 'Permission berhasil diberikan!' : 'Permission berhasil dicabut!';

            $this->loadData($service);
            $this->notifySuccess($message);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk mengubah permission.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function create()
    {
        $this->authorize('create', Role::class);

        $this->resetForm();
        $this->editMode = false;
        $this->showModal = true;
    }

    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $this->authorize('update', $role);

        $this->roleId = $role->id;
        $this->roleName = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();

        $this->editMode = true;
        $this->showModal = true;
    }

    public function save(RolePermissionService $service)
    {
        try {
            $this->validate([
                'roleName' => ['required', 'string', 'max:255', $this->editMode ? 'unique:roles,name,'.$this->roleId : 'unique:roles,name'],
                'selectedPermissions' => 'nullable|array',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            if ($this->editMode) {
                $role = Role::findOrFail($this->roleId);
                $this->authorize('update', $role);

                $service->updateRole($role, $this->roleName, $this->selectedPermissions);
                $message = 'Role berhasil diupdate!';
            } else {
                $this->authorize('create', Role::class);

                $service->createRole($this->roleName, $this->selectedPermissions);
                $message = 'Role berhasil ditambahkan!';
            }

            $this->loadData($service);
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
        $role = Role::findOrFail($id);
        $this->deletingRoleId = $role->id;
        $this->deletingRoleName = $role->name;
        $this->showDeleteModal = true;
    }

    public function delete(RolePermissionService $service)
    {
        try {
            $role = Role::findOrFail($this->deletingRoleId);
            $this->authorize('delete', $role);

            $service->deleteRole($role);

            if ($this->selectedRole == $this->deletingRoleId) {
                $this->selectedRole = null;
                $this->rolePermissions = [];
            }

            $this->loadData($service);
            $this->notifySuccess('Role berhasil dihapus!');
            $this->showDeleteModal = false;
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Role ini tidak dapat dihapus karena masih digunakan oleh user!');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    private function resetForm()
    {
        $this->reset([
            'roleId',
            'roleName',
            'selectedPermissions',
        ]);
    }

    public function exportExcel()
    {
        $this->authorize('exportExcel', Role::class);

        return (new RolesExport)->download('roles-'.now()->format('Y-m-d-His').'.xlsx');
    }

    public function exportPdf()
    {
        $this->authorize('exportPdf', Role::class);

        $roles = Role::with('permissions')->get();

        $pdf = Pdf::loadView('exports.roles-pdf', ['roles' => $roles]);
        $pdf->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'roles-'.now()->format('Y-m-d-His').'.pdf'
        );
    }

    public function render()
    {
        return view('livewire.settings.role-permission');
    }
}
