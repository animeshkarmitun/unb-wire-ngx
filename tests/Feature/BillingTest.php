<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Package;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mrr_sums_active_packages(): void
    {
        $c1 = Client::factory()->create();
        $c2 = Client::factory()->create();
        $p1 = Package::factory()->create(['price_monthly' => 100]);
        $p2 = Package::factory()->create(['price_monthly' => 200]);
        DB::table('client_packages')->insert(['client_id' => $c1->id, 'package_id' => $p1->id, 'status' => 'active', 'starts_at' => now()]);
        DB::table('client_packages')->insert(['client_id' => $c2->id, 'package_id' => $p2->id, 'status' => 'active', 'starts_at' => now()]);
        $mrr = app(BillingService::class)->mrr();
        $this->assertEquals(300, (float) $mrr);
    }

    public function test_mrr_excludes_expired(): void
    {
        $c = Client::factory()->create();
        $p = Package::factory()->create(['price_monthly' => 100]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p->id, 'status' => 'active', 'starts_at' => now()->subMonth(), 'ends_at' => now()->subDay()]);
        $this->assertEquals(0, (float) app(BillingService::class)->mrr());
    }

    public function test_generate_invoices_idempotent(): void
    {
        $c = Client::factory()->create();
        $p = Package::factory()->create(['price_monthly' => 50]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p->id, 'status' => 'active', 'starts_at' => now()]);
        $svc = app(BillingService::class);
        $n1 = $svc->generateInvoices(now()->toDateString(), now()->toDateString());
        $this->assertEquals(1, $n1);
        $n2 = $svc->generateInvoices(now()->toDateString(), now()->toDateString());
        $this->assertEquals(0, $n2);
        $this->assertDatabaseHas('invoices', ['client_id' => $c->id]);
        $this->assertDatabaseHas('invoice_lines', ['description' => $p->name]);
    }

    public function test_mark_paid(): void
    {
        $c = Client::factory()->create();
        $p = Package::factory()->create(['price_monthly' => 10]);
        DB::table('client_packages')->insert(['client_id' => $c->id, 'package_id' => $p->id, 'status' => 'active', 'starts_at' => now()]);
        app(BillingService::class)->generateInvoices(now()->toDateString(), now()->toDateString());
        $inv = Invoice::first();
        app(BillingService::class)->markPaid($inv);
        $this->assertEquals('paid', $inv->refresh()->status);
        $this->assertNotNull($inv->paid_at);
    }
}
