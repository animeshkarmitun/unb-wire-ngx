<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Package;
use App\Services\Search\EntitlementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(EntitlementResolver::class);
    }

    public function test_single_package_entitlement(): void
    {
        $c = Client::factory()->create();
        $p = Package::factory()->create(['entitlement_filter' => ['languages' => ['en'], 'category_ids' => [1], 'media_kinds' => ['photo']]]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p->id, 'status' => 'active', 'starts_at' => now()->subDay()]);
        $e = $this->resolver->forClient($c);
        $this->assertEquals(['en'], $e['languages']);
        $this->assertEquals([1], $e['category_ids']);
        $this->assertEquals(['photo'], $e['media_kinds']);
    }

    public function test_union_two_packages(): void
    {
        $c = Client::factory()->create();
        $p1 = Package::factory()->create(['entitlement_filter' => ['languages' => ['en'], 'category_ids' => [5]]]);
        $p2 = Package::factory()->create(['entitlement_filter' => ['languages' => ['bn'], 'category_ids' => [7], 'media_kinds' => ['video']]]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p1->id, 'status' => 'active', 'starts_at' => now()]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p2->id, 'status' => 'active', 'starts_at' => now()]);
        $e = $this->resolver->forClient($c);
        $this->assertEqualsCanonicalizing(['en', 'bn'], $e['languages']);
        $this->assertEqualsCanonicalizing([5, 7], $e['category_ids']);
        $filter = $this->resolver->compileMeiliFilter($c);
        $this->assertStringContainsString('language IN [en, bn]', $filter);
        $this->assertStringContainsString('category_id IN [5, 7]', $filter);
    }

    public function test_expired_package_excluded(): void
    {
        $c = Client::factory()->create();
        $p = Package::factory()->create(['entitlement_filter' => ['languages' => ['en']]]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        $e = $this->resolver->forClient($c);
        $this->assertEquals(['en', 'bn'], $e['languages']);
        $this->assertNull($e['category_ids']);
    }

    public function test_no_packages_defaults(): void
    {
        $c = Client::factory()->create();
        $e = $this->resolver->forClient($c);
        $this->assertEquals(['en', 'bn'], $e['languages']);
    }

    public function test_compile_from_entitlement(): void
    {
        $f = $this->resolver->compileMeiliFilterFromEntitlement(['languages' => ['en'], 'category_ids' => [10, 20]]);
        $this->assertEquals('language IN [en] AND category_id IN [10, 20]', $f);
        $f2 = $this->resolver->compileMeiliFilterFromEntitlement(['languages' => ['bn']]);
        $this->assertEquals('language IN [bn]', $f2);
    }
}
