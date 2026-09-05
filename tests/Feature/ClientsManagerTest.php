<?php

namespace Tests\Feature;

use App\Livewire\Admin\ClientsManager;
use App\Models\Client;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClientSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ClientsManagerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PackageSeeder::class);
        $this->seed(ClientSeeder::class);

        $adminRole = Role::where('name', 'Admin')->firstOrFail();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($this->admin);
    }

    public function test_clients_manager_renders_and_computes_exact_canonical_stats(): void
    {
        $response = $this->get('/admin/clients');
        $response->assertOk();

        Livewire::test(ClientsManager::class)
            ->assertSet('stats.total', 8)
            ->assertSet('stats.active', 5)
            ->assertSet('stats.paused', 2)
            ->assertSet('stats.renew', 3)
            ->assertSet('stats.issues', 1)
            ->assertSee('The Daily Star')
            ->assertSee('Prothom Alo')
            ->assertSee('Jugantor')
            ->assertSee('Bangladesh Today')
            ->assertSee('The Daily Ittefaq')
            ->assertSee('Dhaka Tribune')
            ->assertSee('Samakal')
            ->assertSee('The Financial Express');
    }

    public function test_search_filters_by_name_and_code(): void
    {
        Livewire::test(ClientsManager::class)
            ->set('search', 'Jugantor')
            ->assertSee('Jugantor')
            ->assertDontSee('The Daily Star')
            ->set('search', 'PAL')
            ->assertSee('Prothom Alo')
            ->assertDontSee('Samakal');
    }

    public function test_status_chips_filter_clients(): void
    {
        Livewire::test(ClientsManager::class)
            ->call('setStatusFilter', 'paused')
            ->assertSee('Bangladesh Today')
            ->assertSee('Samakal')
            ->assertDontSee('The Daily Star')
            ->call('setStatusFilter', 'deactivated')
            ->assertSee('The Financial Express')
            ->assertDontSee('Prothom Alo')
            ->call('setStatusFilter', 'active')
            ->assertSee('The Daily Star')
            ->assertDontSee('The Financial Express');
    }

    public function test_tier_filter_and_sorting(): void
    {
        Livewire::test(ClientsManager::class)
            ->set('tierFilter', 'Premium')
            ->assertSee('The Daily Star')
            ->assertSee('Prothom Alo')
            ->assertSee('Dhaka Tribune')
            ->assertDontSee('Samakal')
            ->set('tierFilter', 'all')
            ->set('sort', 'renewal')
            ->assertOk()
            ->set('sort', 'usage')
            ->assertOk();
    }

    public function test_multi_selection_and_bulk_pause(): void
    {
        $dailyStar = Client::where('name', 'The Daily Star')->firstOrFail();
        $prothomAlo = Client::where('name', 'Prothom Alo')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('toggleSelect', $dailyStar->id)
            ->call('toggleSelect', $prothomAlo->id)
            ->assertSet('selectedIds', [$dailyStar->id, $prothomAlo->id])
            ->call('bulkPause')
            ->assertDispatched('toast')
            ->assertSet('selectedIds', []);

        $this->assertEquals('suspended', $dailyStar->fresh()->status);
        $this->assertEquals('suspended', $prothomAlo->fresh()->status);
    }

    public function test_bulk_package_change(): void
    {
        $jugantor = Client::where('name', 'Jugantor')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('toggleSelect', $jugantor->id)
            ->set('bulkPackage', 'Premium Wire + Media')
            ->call('bulkApplyPackage')
            ->assertDispatched('toast');

        $meta = json_decode($jugantor->fresh()->notes, true);
        $this->assertEquals('Premium Wire + Media', $meta['package_name']);
        $this->assertEquals('Premium', $meta['tier']);
    }

    public function test_drawer_overview_and_note_saving(): void
    {
        $dailyStar = Client::where('name', 'The Daily Star')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $dailyStar->id)
            ->assertSet('selectedId', $dailyStar->id)
            ->assertSet('drawerTab', 'overview')
            ->assertSee('Prefers XML push for breaking news')
            ->set('clientNoteText', 'Updated newsroom VIP contact note')
            ->call('saveNote')
            ->assertDispatched('toast', message: 'Note saved');

        $meta = json_decode($dailyStar->fresh()->notes, true);
        $this->assertEquals('Updated newsroom VIP contact note', $meta['note_text']);
    }

    public function test_drawer_channels_email_and_ftp_interaction(): void
    {
        $dailyStar = Client::where('name', 'The Daily Star')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $dailyStar->id)
            ->call('setDrawerTab', 'channels')
            ->set('newEmailRecipient', 'alerts@thedailystar.net')
            ->call('addEmailRecipient')
            ->assertDispatched('toast', message: 'Recipient added')
            ->call('removeEmailRecipient', 'alerts@thedailystar.net')
            ->assertDispatched('toast', message: 'Recipient removed')
            ->call('testFtpConnection')
            ->assertDispatched('toast');

        $jugantor = Client::where('name', 'Jugantor')->firstOrFail();
        $this->assertTrue($jugantor->clientChannels()->where('type', 'ftp')->first()->failure_count > 0);

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $jugantor->id)
            ->call('setDrawerTab', 'channels')
            ->call('testFtpConnection')
            ->assertDispatched('toast');

        $this->assertEquals(0, $jugantor->fresh()->clientChannels()->where('type', 'ftp')->first()->failure_count);
    }

    public function test_drawer_channels_api_key_regeneration(): void
    {
        $dailyStar = Client::where('name', 'The Daily Star')->firstOrFail();

        $comp = Livewire::test(ClientsManager::class)
            ->call('selectClient', $dailyStar->id)
            ->call('setDrawerTab', 'channels')
            ->call('toggleApiKeyReveal')
            ->assertSet('showApiKey', true)
            ->call('regenerateApiKey')
            ->assertSet('confirmRegen', true)
            ->call('regenerateApiKey')
            ->assertSet('confirmRegen', false)
            ->assertDispatched('toast');

        $newKey = $comp->get('apiKey');
        $this->assertStringStartsWith('unb_live_', $newKey);
    }

    public function test_drawer_package_change_with_addons(): void
    {
        $samakal = Client::where('name', 'Samakal')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('selectClient', $samakal->id)
            ->call('setDrawerTab', 'package')
            ->set('drawerPackageName', 'Premium Wire + Media')
            ->set('drawerAddons', ['AP World pack', 'Bangla service'])
            ->call('applyPackageChange')
            ->assertDispatched('toast', message: '<b>Samakal</b> package updated');

        $meta = json_decode($samakal->fresh()->notes, true);
        $this->assertEquals('Premium Wire + Media', $meta['package_name']);
        $this->assertEquals('Premium', $meta['tier']);
        $this->assertContains('AP World pack', $meta['addons']);
    }

    public function test_pause_and_resume_workflows(): void
    {
        $dailyStar = Client::where('name', 'The Daily Star')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('openPauseModal', $dailyStar->id)
            ->assertSet('showPauseModal', true)
            ->set('pauseReason', 'Payment hold')
            ->set('pauseNote', 'Accounts checking invoice')
            ->call('confirmPause')
            ->assertSet('showPauseModal', false)
            ->assertDispatched('toast');

        $this->assertEquals('suspended', $dailyStar->fresh()->status);

        Livewire::test(ClientsManager::class)
            ->call('resumeClient', $dailyStar->id)
            ->assertDispatched('toast');

        $this->assertEquals('active', $dailyStar->fresh()->status);
    }

    public function test_deactivate_and_reactivate_workflows(): void
    {
        $ittefaq = Client::where('name', 'The Daily Ittefaq')->firstOrFail();

        Livewire::test(ClientsManager::class)
            ->call('openDeactModal', $ittefaq->id)
            ->assertSet('showDeactModal', true)
            ->set('deactConfirmed', true)
            ->call('confirmDeactivate')
            ->assertSet('showDeactModal', false)
            ->assertDispatched('toast');

        $this->assertEquals('closed', $ittefaq->fresh()->status);

        Livewire::test(ClientsManager::class)
            ->call('reactivateClient', $ittefaq->id)
            ->assertDispatched('toast');

        $this->assertEquals('active', $ittefaq->fresh()->status);
    }

    public function test_onboard_wizard_navigation_and_activation(): void
    {
        Livewire::test(ClientsManager::class)
            ->call('openOnboard')
            ->assertSet('showOnboardModal', true)
            ->assertSet('wizStep', 1)
            ->call('wizNext') // Should fail validation because name is empty
            ->assertHasErrors(['wName', 'wEmail'])
            ->set('wName', 'Bonik Barta')
            ->set('wEmail', 'news@bonikbarta.com')
            ->set('wCity', 'Dhaka')
            ->set('wContact', 'Editorial Desk')
            ->call('wizNext')
            ->assertSet('wizStep', 2)
            ->set('wPackage', 'Standard Wire')
            ->set('wAddons', ['Bangla service'])
            ->set('wChannels', ['email', 'ftp'])
            ->call('wizNext')
            ->assertSet('wizStep', 3)
            ->call('activateClient')
            ->assertSet('showOnboardModal', false)
            ->assertDispatched('toast');

        $newClient = Client::where('name', 'Bonik Barta')->firstOrFail();
        $this->assertEquals('active', $newClient->status);
        $this->assertEquals('news@bonikbarta.com', $newClient->billing_email);
        $this->assertDatabaseHas('client_users', ['email' => 'news@bonikbarta.com', 'client_id' => $newClient->id]);
        $this->assertDatabaseHas('client_channels', ['client_id' => $newClient->id, 'type' => 'ftp']);
    }

    public function test_csv_export_returns_streamed_response(): void
    {
        $comp = Livewire::test(ClientsManager::class);
        $response = $comp->instance()->exportCsv(false);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('unb-clients.csv', $response->headers->get('Content-Disposition'));
    }
}
