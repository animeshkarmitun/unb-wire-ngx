<?php

namespace Tests\Feature;

use App\Livewire\Admin\DeliverySettings;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliverySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $uploaderUser;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->adminUser = User::where('email', 'nahar@unbnews.org')->firstOrFail();
        $this->uploaderUser = User::where('email', 'maria@unbnews.org')->firstOrFail();
        $this->client = Client::where('code', 'DST')->firstOrFail();
    }

    public function test_delivery_settings_page_loads_for_authorized_user(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/delivery-settings');

        $response->assertStatus(200);
        $response->assertSeeLivewire(DeliverySettings::class);
        $response->assertSee('Delivery settings');
        $response->assertSee('Auto-push (FTP / SFTP)');
        $response->assertSee('API access');
        $response->assertSee('Email alerts');
        $response->assertSee('Download &amp; license history', false);
        $response->assertSee('Engine dispatch rules');
    }

    public function test_unauthorized_user_is_blocked_by_rbac(): void
    {
        $response = $this->actingAs($this->uploaderUser)->get('/admin/delivery-settings');

        $response->assertStatus(403);
    }

    public function test_client_context_switching_loads_client_channels(): void
    {
        $otherClient = Client::where('id', '!=', $this->client->id)->firstOrFail();

        Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->assertSet('selectedClientId', $this->client->id)
            ->call('selectClient', $otherClient->id)
            ->assertSet('selectedClientId', $otherClient->id);
    }

    public function test_save_push_settings_updates_channel_config(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->set('wireFormat', 'JSON (UNB v1)')
            ->set('pushSchedule', 'Batch — every 15 minutes')
            ->set('chanEnglishWire', true)
            ->set('chanUnbPhotos', false)
            ->call('savePushSettings')
            ->assertDispatched('toast', message: '✓ Push settings saved — JSON (UNB v1)');

        $channel = ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first();
        $this->assertNotNull($channel);
        $this->assertEquals('JSON (UNB v1)', $channel->config['wire_format']);
        $this->assertEquals('Batch — every 15 minutes', $channel->config['push_schedule']);
        $this->assertFalse($channel->config['channels']['unb_photos']);
    }

    public function test_save_credentials_updates_sftp_config(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->call('toggleCreds')
            ->assertSet('showCreds', true)
            ->set('sftpHost', 'ftp.newhost.net')
            ->set('sftpPort', '2222')
            ->set('sftpUser', 'daily-wire')
            ->set('sftpAuth', 'Password')
            ->call('saveCredentials')
            ->assertSet('showCreds', false)
            ->assertDispatched('toast', message: '✓ Credentials saved — reconnecting…');

        $channel = ClientChannel::where('client_id', $this->client->id)->where('type', 'ftp')->first();
        $this->assertNotNull($channel);
        $this->assertEquals('ftp.newhost.net', $channel->config['host']);
        $this->assertEquals('2222', $channel->config['port']);
        $this->assertEquals('daily-wire', $channel->config['username']);
    }

    public function test_api_key_reveal_and_two_step_rotation(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->assertSet('isKeyRevealed', false)
            ->call('toggleRevealKey')
            ->assertSet('isKeyRevealed', true)
            ->call('toggleRevealKey')
            ->assertSet('isKeyRevealed', false);

        // Step 1: Request rotation
        $component->call('requestRegenerateKey')
            ->assertSet('regenStep', 1);

        // Step 2: Confirm rotation
        $component->call('confirmRegenerateKey')
            ->assertSet('regenStep', 0)
            ->assertDispatched('toast', message: '✓ Old key revoked — update your CMS plugin with the new key');
    }

    public function test_save_api_settings_updates_webhook_and_triggers(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->set('webhookUrl', 'https://cms.dailystar.com/webhooks/unb-v2')
            ->set('notifyBreaking', true)
            ->set('notifyEmbargoed', true)
            ->call('saveApiSettings')
            ->assertDispatched('toast', message: '✓ API settings saved');

        $channel = ClientChannel::where('client_id', $this->client->id)->where('type', 'webhook')->first();
        $this->assertNotNull($channel);
        $this->assertEquals('https://cms.dailystar.com/webhooks/unb-v2', $channel->config['webhook']);
        $this->assertTrue($channel->config['triggers']['embargoed']);
    }

    public function test_email_recipient_add_and_remove_with_validation(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            // Invalid email validation
            ->set('emailInput', 'invalid-email')
            ->call('addEmailRecipient')
            ->assertDispatched('toast', message: 'Enter a valid email address')
            // Valid email addition
            ->set('emailInput', 'lead-editor@dailystar.com')
            ->call('addEmailRecipient')
            ->assertDispatched('toast', message: '✓ lead-editor@dailystar.com added to alerts');

        $recipients = $component->get('emailRecipients');
        $this->assertContains('lead-editor@dailystar.com', $recipients);

        // Remove recipient
        $index = array_search('lead-editor@dailystar.com', $recipients);
        $component->call('removeEmailRecipient', $index)
            ->assertDispatched('toast', message: 'Recipient removed');

        $this->assertNotContains('lead-editor@dailystar.com', $component->get('emailRecipients'));
    }

    public function test_save_alert_settings_updates_email_alerts(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->set('alertBreaking', true)
            ->set('alertSavedSearch', true)
            ->call('saveAlertSettings')
            ->assertDispatched('toast', message: '✓ Alert settings saved');

        $client = Client::find($this->client->id);
        $notes = is_string($client->notes) ? json_decode($client->notes, true) : $client->notes;
        $this->assertTrue($notes['channels']['email']['alerts']['saved_search']);
    }

    public function test_download_history_renders_and_exports_csv(): void
    {
        $component = Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class);

        $downloads = $component->get('downloads');
        $this->assertNotEmpty($downloads);

        $component->call('exportCsv')
            ->assertFileDownloaded('unb-license-history.csv');
    }

    public function test_save_engine_rules_updates_delivery_setting(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(DeliverySettings::class)
            ->set('retryAttempts', 5)
            ->set('backoffSeconds', 120)
            ->set('autoPauseAfter', 8)
            ->set('atLeastOnce', true)
            ->call('saveEngineRules')
            ->assertDispatched('toast', message: 'Delivery settings saved');

        $setting = Setting::where('key', 'delivery')->first();
        $this->assertNotNull($setting);
        $val = is_string($setting->value) ? json_decode($setting->value, true) : $setting->value;
        $this->assertEquals(5, $val['retryAttempts']);
        $this->assertEquals(120, $val['backoffSeconds']);
        $this->assertEquals(8, $val['autoPauseAfter']);
        $this->assertTrue($val['atLeastOnce']);
    }
}
