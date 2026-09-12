<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationCenter extends Component
{
    use WithPagination;

    public string $filter = 'all'; // 'all' | 'unread'

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function markRead(string $id): void
    {
        $notification = Auth::user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public static function deepLink(array $data, string $event): string
    {
        if (isset($data['story_id'])) {
            $url = '/admin/news/en';
            if (str_contains($event, 'note')) {
                $url .= '#notes';
            }

            return $url;
        }

        if (isset($data['batch_id'])) {
            return '/admin/photos';
        }

        return '/admin';
    }

    public function render()
    {
        $query = Auth::user()->notifications();

        if ($this->filter === 'unread') {
            $query = Auth::user()->unreadNotifications();
        }

        return view('livewire.admin.notification-center', [
            'notifications' => $query->latest()->paginate(15),
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ]);
    }
}
