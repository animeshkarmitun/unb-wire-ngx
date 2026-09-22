<?php

namespace Tests\Feature\Repositories;

use App\Models\Package;
use App\Repositories\PackageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PackageRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(PackageRepository::class);
    }

    public function test_find_or_fail_returns_package(): void
    {
        $pkg = Package::factory()->create();

        $result = $this->repo->findOrFail($pkg->id);

        $this->assertEquals($pkg->id, $result->id);
    }

    public function test_find_by_code_returns_package(): void
    {
        Package::factory()->create(['code' => 'TEST-PKG']);

        $result = $this->repo->findByCode('TEST-PKG');

        $this->assertNotNull($result);
        $this->assertEquals('TEST-PKG', $result->code);
    }

    public function test_find_by_code_returns_null_for_missing(): void
    {
        $result = $this->repo->findByCode('NONEXISTENT');

        $this->assertNull($result);
    }

    public function test_code_exists_returns_true_when_exists(): void
    {
        Package::factory()->create(['code' => 'EXISTING']);

        $this->assertTrue($this->repo->codeExists('EXISTING'));
    }

    public function test_code_exists_returns_false_when_missing(): void
    {
        $this->assertFalse($this->repo->codeExists('MISSING'));
    }

    public function test_code_exists_excludes_id(): void
    {
        $pkg = Package::factory()->create(['code' => 'SAME']);

        $this->assertFalse($this->repo->codeExists('SAME', $pkg->id));
    }

    public function test_all_returns_all_packages(): void
    {
        Package::factory()->count(3)->create();

        $result = $this->repo->all();

        $this->assertCount(3, $result);
    }

    public function test_active_packages_filters_by_status(): void
    {
        Package::factory()->create(['status' => 'active']);
        Package::factory()->create(['status' => 'archived']);

        $result = $this->repo->activePackages();

        $this->assertCount(1, $result);
    }

    public function test_create_creates_package(): void
    {
        $result = $this->repo->create([
            'name' => 'New Pkg',
            'code' => 'NEW-PKG',
            'kind' => 'news',
            'entitlement_filter' => ['languages' => ['en']],
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Package::class, $result);
        $this->assertDatabaseHas('packages', ['code' => 'NEW-PKG']);
    }

    public function test_update_updates_package(): void
    {
        $pkg = Package::factory()->create(['name' => 'Old']);

        $result = $this->repo->update($pkg, ['name' => 'New']);

        $this->assertEquals('New', $result->name);
    }

    public function test_delete_deletes_package(): void
    {
        $pkg = Package::factory()->create();

        $result = $this->repo->delete($pkg);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('packages', ['id' => $pkg->id]);
    }
}
