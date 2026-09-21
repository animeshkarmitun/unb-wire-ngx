<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_has_fillable_attributes(): void
    {
        $assignment = Assignment::factory()->create([
            'title' => 'Test Assignment',
            'description' => 'Test description',
            'location' => 'Dhaka',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        $this->assertEquals('Test Assignment', $assignment->title);
        $this->assertEquals('Test description', $assignment->description);
        $this->assertEquals('Dhaka', $assignment->location);
        $this->assertEquals('high', $assignment->priority);
        $this->assertEquals('pending', $assignment->status);
    }

    public function test_assignment_casts_shot_list_to_array(): void
    {
        $assignment = Assignment::factory()->create(['shot_list' => ['photo1', 'photo2']]);

        $this->assertIsArray($assignment->shot_list);
        $this->assertCount(2, $assignment->shot_list);
    }

    public function test_assignment_casts_due_at_to_datetime(): void
    {
        $assignment = Assignment::factory()->create(['due_at' => '2026-12-01 10:00:00']);

        $this->assertInstanceOf(Carbon::class, $assignment->due_at);
    }

    public function test_assignment_belongs_to_assignee(): void
    {
        $user = User::factory()->create();
        $assignment = Assignment::factory()->create(['assignee_id' => $user->id]);

        $this->assertInstanceOf(User::class, $assignment->assignee);
        $this->assertEquals($user->id, $assignment->assignee->id);
    }

    public function test_assignment_belongs_to_creator(): void
    {
        $user = User::factory()->create();
        $assignment = Assignment::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(User::class, $assignment->creator);
        $this->assertEquals($user->id, $assignment->creator->id);
    }

    public function test_assignment_has_many_batches(): void
    {
        $assignment = Assignment::factory()->create();

        $this->assertInstanceOf(HasMany::class, $assignment->batches());
    }

    public function test_assignment_factory_creates_valid_model(): void
    {
        $assignment = Assignment::factory()->create();

        $this->assertInstanceOf(Assignment::class, $assignment);
        $this->assertNotNull($assignment->id);
        $this->assertNotNull($assignment->title);
    }
}
