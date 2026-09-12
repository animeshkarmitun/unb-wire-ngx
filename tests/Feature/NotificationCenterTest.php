<?php

namespace Tests\Feature;

use App\Livewire\Admin\NotificationCenter;
use App\Models\Role;
use App\Models\User;
use App\Notifications\StoryNotification;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
    }

    public function test_notification_center_page_requires_auth(): void
    {
        $this->get('/admin/notifications')->assertRedirect('/login');
    }

    public function test_notification_center_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);

        $this->actingAs($user)
            ->get('/admin/notifications')
            ->assertOk()
            ->assertSeeLivewire('admin.notification-center');
    }

    public function test_mark_read_updates_notification(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $user->notify(new StoryNotification('review_requested', ['story_id' => 1, 'headline' => 'Test', 'actor_id' => 1]));

        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->call('markRead', $notification->id);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    public function test_mark_all_read_clears_unread(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $user->notify(new StoryNotification('review_requested', ['story_id' => 1, 'headline' => 'Test 1', 'actor_id' => 1]));
        $user->notify(new StoryNotification('approved', ['story_id' => 2, 'headline' => 'Test 2', 'actor_id' => 1]));

        $this->assertEquals(2, $user->unreadNotifications()->count());

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->call('markAllRead');

        $this->assertEquals(0, $user->fresh()->unreadNotifications()->count());
    }

    public function test_deep_link_resolves_for_story_events(): void
    {
        $link = NotificationCenter::deepLink(['story_id' => 1], 'approved');
        $this->assertEquals('/admin/news/en', $link);
    }

    public function test_deep_link_resolves_for_note_events(): void
    {
        $link = NotificationCenter::deepLink(['story_id' => 1], 'note_added');
        $this->assertEquals('/admin/news/en#notes', $link);
    }

    public function test_deep_link_resolves_for_media_events(): void
    {
        $link = NotificationCenter::deepLink(['batch_id' => 5], 'media_approved');
        $this->assertEquals('/admin/photos', $link);
    }

    public function test_filter_shows_only_unread(): void
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'Editor')->first()->id]);
        $user->notify(new StoryNotification('review_requested', ['story_id' => 1, 'headline' => 'Unread', 'actor_id' => 1]));
        $user->notify(new StoryNotification('approved', ['story_id' => 2, 'headline' => 'Read', 'actor_id' => 1]));

        // Mark second as read
        $user->notifications()->where('id', $user->notifications()->latest()->first()->id)->update(['read_at' => now()]);

        Livewire::actingAs($user)
            ->test(NotificationCenter::class)
            ->set('filter', 'unread')
            ->assertSee('Unread');
    }
}
