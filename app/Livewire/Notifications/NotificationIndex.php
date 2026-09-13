<?php

namespace App\Livewire\Notifications;

use App\Livewire\Traits\HasNotification;
use App\Models\Notification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationIndex extends Component
{
    use AuthorizesRequests, HasNotification, WithPagination;

    public string $filter = 'all'; // all, unread, read

    // Delete Modal
    public bool $showDeleteModal = false;

    public ?int $deletingNotificationId = null;

    public ?string $deletingNotificationTitle = null;

    // Delete All Read Modal
    public bool $showDeleteAllModal = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Notification::class);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', Auth::id())
            ->first();

        if ($notification) {
            $notification->markAsRead();
            $this->dispatch('notifications-read');
            $this->notifySuccess('Notifikasi ditandai sudah dibaca.');
        }
    }

    public function markAllAsRead(): void
    {
        Notification::forUser(Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        $this->dispatch('notifications-read');
        $this->notifySuccess('Semua notifikasi ditandai sudah dibaca.');
    }

    public function confirmDeleteNotification(int $notificationId): void
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', Auth::id())
            ->first();

        if ($notification) {
            $this->deletingNotificationId = $notification->id;
            $this->deletingNotificationTitle = $notification->title;
            $this->showDeleteModal = true;
        }
    }

    public function deleteNotification(): void
    {
        Notification::where('id', $this->deletingNotificationId)
            ->where('user_id', Auth::id())
            ->delete();

        $this->showDeleteModal = false;
        $this->deletingNotificationId = null;
        $this->deletingNotificationTitle = null;
        $this->dispatch('notifications-read');
        $this->notifySuccess('Notifikasi berhasil dihapus.');
    }

    public function confirmDeleteAllRead(): void
    {
        $this->showDeleteAllModal = true;
    }

    public function deleteAllRead(): void
    {
        Notification::forUser(Auth::id())
            ->read()
            ->delete();

        $this->showDeleteAllModal = false;
        $this->dispatch('notifications-read');
        $this->notifySuccess('Semua notifikasi yang sudah dibaca berhasil dihapus.');
    }

    public function getListeners(): array
    {
        $userId = Auth::id();

        return [
            "echo-private:user.{$userId},NewNotification" => '$refresh',
            'notifications-read' => '$refresh',
        ];
    }

    public function render()
    {
        $query = Notification::forUser(Auth::id())->latest();

        if ($this->filter === 'unread') {
            $query->unread();
        } elseif ($this->filter === 'read') {
            $query->read();
        }

        return view('livewire.notifications.notification-index', [
            'notifications' => $query->paginate(15),
            'unreadCount' => Notification::forUser(Auth::id())->unread()->count(),
        ]);
    }
}
