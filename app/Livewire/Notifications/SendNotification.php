<?php

namespace App\Livewire\Notifications;

use App\Livewire\Traits\HasNotification;
use App\Models\User;
use App\Services\NotificationService;
use App\Traits\HasDynamicLike;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

class SendNotification extends Component
{
    use AuthorizesRequests, HasDynamicLike, HasNotification, WithPagination;

    // Form fields
    public string $target = 'all'; // 'all' | 'specific'

    public array $selectedUserIds = [];

    public string $type = 'info';

    public string $title = '';

    public string $notifMessage = '';

    public string $actionUrl = '';

    // User search
    public string $userSearch = '';

    // History pagination
    public string $historySearch = '';

    // Tab
    public string $activeTab = 'compose';

    protected function rules(): array
    {
        return [
            'target' => ['required', 'in:all,specific'],
            'selectedUserIds' => ['required_if:target,specific', 'array'],
            'selectedUserIds.*' => ['exists:users,id'],
            'type' => ['required', 'in:info,success,warning,danger'],
            'title' => ['required', 'string', 'max:255'],
            'notifMessage' => ['required', 'string', 'max:2000'],
            'actionUrl' => ['nullable', 'url', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Judul notifikasi wajib diisi.',
            'title.max' => 'Judul maksimal 255 karakter.',
            'notifMessage.required' => 'Isi pesan wajib diisi.',
            'notifMessage.max' => 'Pesan maksimal 2000 karakter.',
            'selectedUserIds.required_if' => 'Pilih minimal satu penerima.',
            'actionUrl.url' => 'URL aksi harus berupa URL yang valid.',
        ];
    }

    public function updatedTarget(): void
    {
        $this->selectedUserIds = [];
        $this->resetValidation('selectedUserIds');
    }

    public function updatedUserSearch(): void
    {
        $this->resetPage('usersPage');
    }

    public function updatedHistorySearch(): void
    {
        $this->resetPage('historyPage');
    }

    public function toggleUser(int $userId): void
    {
        if (in_array($userId, $this->selectedUserIds)) {
            $this->selectedUserIds = array_values(
                array_filter($this->selectedUserIds, fn ($id) => $id !== $userId)
            );
        } else {
            $this->selectedUserIds[] = $userId;
        }
    }

    public function removeUser(int $userId): void
    {
        $this->selectedUserIds = array_values(
            array_filter($this->selectedUserIds, fn ($id) => $id !== $userId)
        );
    }

    public function send(): void
    {
        $this->authorize('notifications_send');

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            if ($this->target === 'all') {
                NotificationService::sendToAll(
                    title: $this->title,
                    message: $this->notifMessage,
                    type: $this->type,
                    actionUrl: $this->actionUrl ?: null,
                );

                $recipientCount = User::active()->count();
                $this->notifySuccess("Notifikasi berhasil dikirim ke {$recipientCount} pengguna.");
            } else {
                NotificationService::sendToMany(
                    userIds: $this->selectedUserIds,
                    title: $this->title,
                    message: $this->notifMessage,
                    type: $this->type,
                    actionUrl: $this->actionUrl ?: null,
                );

                $recipientCount = count($this->selectedUserIds);
                $this->notifySuccess("Notifikasi berhasil dikirim ke {$recipientCount} pengguna.");
            }

            $this->resetForm();
            $this->activeTab = 'history';
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan sistem. Silakan coba lagi.');
        }
    }

    public function resetForm(): void
    {
        $this->reset(['title', 'notifMessage', 'actionUrl', 'selectedUserIds', 'userSearch']);
        $this->target = 'all';
        $this->type = 'info';
        $this->resetValidation();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render()
    {
        $this->authorize('notifications_send');

        $operator = $this->getLikeOperator();
        $users = User::active()
            ->when($this->userSearch, fn ($q) => $q->where(function ($q) use ($operator) {
                $q->where('name', $operator, "%{$this->userSearch}%")
                    ->orWhere('email', $operator, "%{$this->userSearch}%");
            }))
            ->orderBy('name')
            ->paginate(8, pageName: 'usersPage');

        $selectedUsers = User::whereIn('id', $this->selectedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $history = \App\Models\Notification::with('user:id,name')
            ->when($this->historySearch, fn ($q) => $q->where(function ($q) use ($operator) {
                $q->where('title', $operator, "%{$this->historySearch}%")
                    ->orWhere('message', $operator, "%{$this->historySearch}%");
            }))
            ->latest()
            ->paginate(15, pageName: 'historyPage');

        $historyStats = \App\Models\Notification::selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $historyStats = collect(['info', 'success', 'warning', 'danger'])
            ->map(fn ($t) => ['type' => $t, 'count' => $historyStats[$t] ?? 0])
            ->all();

        return view('livewire.notifications.send-notification', [
            'users' => $users,
            'selectedUsers' => $selectedUsers,
            'totalUsers' => User::active()->count(),
            'history' => $history,
            'historyStats' => $historyStats,
        ]);
    }
}
