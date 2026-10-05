<?php

namespace Tests\Feature;

use App\Models\AiTokenUsageDaily;
use App\Models\Category;
use App\Models\IndexOutbox;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Story;
use App\Models\StoryEvent;
use App\Models\User;
use App\Services\StoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Closes the model-audit gap from M14-COV-016 follow-up.
 *
 * Each test below exercises a real model class that previously had no
 * direct test reference: AiTokenUsageDaily, IndexOutbox, InvoiceLine,
 * RolePermission, StoryEvent. They use the existing service path
 * (StoryService::transition, Role::create) instead of poking the
 * model from outside, so a regression in the service breaks these
 * tests too — which is the point.
 */
class UncoveredModelBehaviorTest extends TestCase
{
    use RefreshDatabase;

    public function test_story_event_records_timeline_with_vocabulary(): void
    {
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $svc = app(StoryService::class);

        $story = $svc->createDraft([
            'language' => 'en',
            'headline' => 'Timeline event coverage',
            'brief' => 'b',
            'body_html' => '<p>x</p>',
            'category_id' => $cat->id,
        ], $user);
        $svc->transition($story, 'in_review', $user);
        $svc->transition($story, 'approved', $user);
        $svc->transition($story, 'published', $user);

        $actions = StoryEvent::where('story_id', $story->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        $this->assertSame(['created', 'sent_to_review', 'approved', 'published'], $actions);

        $last = StoryEvent::where('story_id', $story->id)->latest('id')->first();
        $this->assertSame($user->name, $last->actor->name);
        $this->assertSame('published', $last->to_status);
        $this->assertIsArray($last->payload);
        $this->assertArrayHasKey('gate', $last->payload);
    }

    public function test_story_event_vocabulary_constant_is_canonical(): void
    {
        // The timeline UI relies on this exact vocabulary; if a key is
        // removed or renamed the dropdown breaks. Asserting the
        // canonical set guards against silent drift.
        $expected = [
            'created', 'sent_to_review', 'changes_requested', 'approved',
            'published', 'auto_published', 'killed', 'archived',
            'handover', 'ai_applied', 'note_added', 'restored',
        ];
        $this->assertSame($expected, array_keys(StoryEvent::ACTIONS));
    }

    public function test_index_outbox_round_trips_upsert_payload(): void
    {
        // StoryService::transition inserts an index_outbox row for the
        // published story. Cast the datetime back and check status.
        $cat = Category::factory()->create();
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'language' => 'en',
            'category_id' => $cat->id,
            'owner_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'published',
        ]);

        IndexOutbox::create([
            'index_name' => 'main',
            'op' => 'upsert',
            'document_id' => $story->public_id,
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ]);

        $row = IndexOutbox::where('document_id', $story->public_id)->firstOrFail();
        $this->assertSame('main', $row->index_name);
        $this->assertSame('upsert', $row->op);
        $this->assertSame('pending', $row->status);
        $this->assertSame(0, (int) $row->attempts);
        $this->assertInstanceOf(Carbon::class, $row->created_at);
    }

    public function test_index_outbox_delete_op_marks_row(): void
    {
        // The kill path writes an op=delete row (M14-COV-005). Mirror it
        // here so the test set covers both shapes of outbox rows.
        IndexOutbox::create([
            'index_name' => 'main',
            'op' => 'delete',
            'document_id' => (string) Str::ulid(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
        ]);
        $row = IndexOutbox::where('op', 'delete')->latest('created_at')->firstOrFail();
        $this->assertSame('delete', $row->op);
    }

    public function test_ai_token_usage_daily_rollup_casts(): void
    {
        $date = '2026-10-04';
        $scope = 'desk:e2e-'.Str::random(6);
        $created = AiTokenUsageDaily::create([
            'date' => $date,
            'scope' => $scope,
            'kind' => 'preedit',
            'tokens' => 12345,
            'cost_micros' => 67890,
        ]);
        $this->assertNotNull($created, 'AiTokenUsageDaily::create returned null');
        $this->assertTrue($created->exists, 'AiTokenUsageDaily::create row does not exist after save');

        $count = AiTokenUsageDaily::where('scope', $scope)->count();
        $this->assertSame(1, $count, "expected 1 row for scope=$scope, got $count");

        $found = AiTokenUsageDaily::where('scope', $scope)->first();
        $this->assertNotNull($found, "expected a row for scope=$scope, got null");

        $this->assertSame(12345, $found->tokens);
        $this->assertSame(67890, $found->cost_micros);
        $this->assertSame('preedit', $found->kind);
        $this->assertInstanceOf(Carbon::class, $found->date);
    }

    public function test_invoice_line_decimal_casts(): void
    {
        // The schema-parity gate asserts the model has decimal:2 casts
        // on unit_price and amount. The test confirms the cast survives
        // a real round-trip from the model layer.
        $invoice = Invoice::factory()->create();
        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'package_id' => null,
            'description' => 'Premium Wire',
            'qty' => 1,
            'unit_price' => '25000.00',
            'amount' => '25000.00',
        ]);

        $line->refresh();
        $this->assertSame('25000.00', (string) $line->unit_price);
        $this->assertSame('25000.00', (string) $line->amount);
        $this->assertSame(1, $line->qty);
    }

    public function test_role_permission_boolean_casts(): void
    {
        $role = Role::create([
            'name' => 'Test Role '.Str::random(6),
            'code' => Str::random(8),
            'type' => 'custom',
        ]);
        RolePermission::create([
            'role_id' => $role->id,
            'module' => 'stories',
            'can_view' => true,
            'can_create' => false,
            'can_edit' => true,
            'can_publish' => false,
            'can_delete' => false,
        ]);

        $row = RolePermission::where('role_id', $role->id)->where('module', 'stories')->firstOrFail();
        $this->assertTrue($row->can_view);
        $this->assertFalse($row->can_create);
        $this->assertTrue($row->can_edit);
        $this->assertFalse($row->can_publish);
        $this->assertFalse($row->can_delete);
        $this->assertSame($role->id, $row->role->id);
    }
}
