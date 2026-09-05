<?php

namespace Tests\Feature;

use App\Livewire\Admin\PackagesManager;
use App\Models\ClientPackage;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ClientSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PackagesManagerTest extends TestCase
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

    public function test_packages_manager_renders_and_computes_exact_canonical_stats(): void
    {
        $response = $this->get('/admin/packages');
        $response->assertOk();

        Livewire::test(PackagesManager::class)
            ->assertSet('stats.live_packages', 3)
            ->assertSet('stats.live_addons', 2)
            ->assertSee('Subscription packages')
            ->assertSee('Add-ons')
            ->assertSee('Premium Wire + Media')
            ->assertSee('Standard Wire')
            ->assertSee('Basic Headlines')
            ->assertSee('District Wire')
            ->assertSee('AP World pack')
            ->assertSee('Bangla service')
            ->assertSee('Sports data feed');
    }

    public function test_package_creation_as_draft_and_published(): void
    {
        // 1. Create as draft
        Livewire::test(PackagesManager::class)
            ->call('openPkgModal')
            ->set('pkgName', 'Special Election Wire')
            ->set('pkgPrice', '35000')
            ->set('pkgGrad', 'g3')
            ->set('pkgWire', 'Full wire — all categories')
            ->set('pkgQuota', '2,000 photos')
            ->set('pkgVideo', true)
            ->call('savePkg', 'draft')
            ->assertSet('showPkgModal', false)
            ->assertDispatched('toast');

        $draft = Package::where('name', 'Special Election Wire')->first();
        $this->assertNotNull($draft);
        $this->assertEquals('archived', $draft->status);
        $this->assertEquals('draft', $draft->entitlement_filter['ui_status']);
        $this->assertEquals('g3', $draft->entitlement_filter['grad']);
        $this->assertEquals('2,000 photos', $draft->entitlement_filter['quota']);

        // 2. Create and publish live
        Livewire::test(PackagesManager::class)
            ->call('openPkgModal')
            ->set('pkgName', 'Global Financial Wire')
            ->set('pkgPrice', '65000')
            ->call('savePkg', 'live')
            ->assertSet('showPkgModal', false)
            ->assertDispatched('toast');

        $live = Package::where('name', 'Global Financial Wire')->first();
        $this->assertNotNull($live);
        $this->assertEquals('active', $live->status);
        $this->assertEquals('live', $live->entitlement_filter['ui_status']);
    }

    public function test_package_edit_updates_existing_record(): void
    {
        $pkg = Package::where('code', 'BASIC-NEWS')->firstOrFail();

        Livewire::test(PackagesManager::class)
            ->call('openPkgModal', $pkg->id)
            ->assertSet('pkgName', 'Basic Headlines')
            ->assertSet('pkgPrice', '18000')
            ->set('pkgPrice', '22000')
            ->set('pkgDesc', 'Updated small portal plan')
            ->call('savePkg', 'live')
            ->assertSet('showPkgModal', false)
            ->assertDispatched('toast');

        $pkg->refresh();
        $this->assertEquals('22000.00', (string) $pkg->price_monthly);
        $this->assertEquals('Updated small portal plan', $pkg->description);
    }

    public function test_package_duplicate_flow(): void
    {
        $pkg = Package::where('code', 'STANDARD-NEWS')->firstOrFail();

        Livewire::test(PackagesManager::class)
            ->call('duplicatePkg', $pkg->id)
            ->assertDispatched('toast');

        $copy = Package::where('name', 'Standard Wire (copy)')->first();
        $this->assertNotNull($copy);
        $this->assertEquals('archived', $copy->status);
        $this->assertEquals('draft', $copy->entitlement_filter['ui_status']);
        $this->assertEquals(0, ClientPackage::where('package_id', $copy->id)->count());
    }

    public function test_package_archive_with_client_reassignment(): void
    {
        $stdPkg = Package::where('code', 'STANDARD-NEWS')->firstOrFail();
        $premPkg = Package::where('code', 'PREMIUM-BUNDLE')->firstOrFail();

        $activeClientsBefore = ClientPackage::where('package_id', $stdPkg->id)
            ->where('status', 'active')
            ->count();

        $this->assertGreaterThan(0, $activeClientsBefore);

        Livewire::test(PackagesManager::class)
            ->call('openArchiveModal', $stdPkg->id)
            ->assertSet('showArchiveModal', true)
            ->assertSet('archiveTargetId', $stdPkg->id)
            ->set('reassignPkgId', $premPkg->id)
            ->call('confirmArchive')
            ->assertSet('showArchiveModal', false)
            ->assertDispatched('toast');

        $stdPkg->refresh();
        $this->assertEquals('archived', $stdPkg->status);

        // All active clients should now belong to $premPkg
        $remainingOnStd = ClientPackage::where('package_id', $stdPkg->id)
            ->where('status', 'active')
            ->count();
        $this->assertEquals(0, $remainingOnStd);
    }

    public function test_package_restore_and_delete_safeguards(): void
    {
        $distPkg = Package::where('code', 'DISTRICT-NEWS')->firstOrFail();
        $this->assertEquals('archived', $distPkg->status);

        // 1. Restore
        Livewire::test(PackagesManager::class)
            ->call('restorePkg', $distPkg->id)
            ->assertDispatched('toast');

        $distPkg->refresh();
        $this->assertEquals('active', $distPkg->status);

        // 2. Archive again
        $distPkg->update(['status' => 'archived']);

        // 3. Delete with 0 clients succeeds
        Livewire::test(PackagesManager::class)
            ->call('deletePkg', $distPkg->id)
            ->assertDispatched('toast');

        $this->assertNull(Package::find($distPkg->id));

        // 4. Delete with existing clients is blocked
        $premPkg = Package::where('code', 'PREMIUM-BUNDLE')->firstOrFail();
        Livewire::test(PackagesManager::class)
            ->call('deletePkg', $premPkg->id)
            ->assertDispatched('toast', message: 'Reassign clients before deleting');

        $this->assertNotNull(Package::find($premPkg->id));
    }

    public function test_addon_creation_and_tier_availability(): void
    {
        Livewire::test(PackagesManager::class)
            ->call('openAoModal')
            ->set('aoName', 'Multimedia Infographics Pack')
            ->set('aoPrice', '15000')
            ->set('aoDesc', 'Daily interactive graphics and vector maps')
            ->call('toggleAoTier', 'Basic') // Now contains Premium, Standard, Basic
            ->call('saveAo')
            ->assertSet('showAoModal', false)
            ->assertDispatched('toast');

        $addon = Package::where('name', 'Multimedia Infographics Pack')->first();
        $this->assertNotNull($addon);
        $this->assertTrue($addon->entitlement_filter['is_addon']);
        $this->assertContains('Basic', $addon->entitlement_filter['tiers']);
        $this->assertContains('Premium', $addon->entitlement_filter['tiers']);
    }

    public function test_addon_status_toggle_live_and_draft(): void
    {
        $addon = Package::where('code', 'ADDON-AP-WORLD')->firstOrFail();
        $this->assertEquals('active', $addon->status);

        // Toggle to draft
        Livewire::test(PackagesManager::class)
            ->call('toggleAoStatus', $addon->id)
            ->assertDispatched('toast');

        $addon->refresh();
        $this->assertEquals('archived', $addon->status);
        $this->assertEquals('draft', $addon->entitlement_filter['ui_status']);

        // Toggle back to live
        Livewire::test(PackagesManager::class)
            ->call('toggleAoStatus', $addon->id)
            ->assertDispatched('toast');

        $addon->refresh();
        $this->assertEquals('active', $addon->status);
        $this->assertEquals('live', $addon->entitlement_filter['ui_status']);
    }

    public function test_addon_delete_blocked_when_clients_exist(): void
    {
        $addonWithClients = Package::where('code', 'ADDON-AP-WORLD')->firstOrFail();

        // Should be blocked because clients use it
        Livewire::test(PackagesManager::class)
            ->call('deleteAo', $addonWithClients->id)
            ->assertDispatched('toast');

        $this->assertNotNull(Package::find($addonWithClients->id));

        // Unused draft add-on can be deleted
        $sportsAddon = Package::where('code', 'ADDON-SPORTS')->firstOrFail();
        Livewire::test(PackagesManager::class)
            ->call('deleteAo', $sportsAddon->id)
            ->assertDispatched('toast', message: 'Add-on deleted');

        $this->assertNull(Package::find($sportsAddon->id));
    }

    public function test_rbac_authorization_blocks_unauthorized_users(): void
    {
        $uploaderRole = Role::where('name', 'Uploader-English')->firstOrFail();
        $uploader = User::factory()->create(['role_id' => $uploaderRole->id]);

        $this->actingAs($uploader);

        $response = $this->get('/admin/packages');
        $response->assertForbidden();
    }
}
