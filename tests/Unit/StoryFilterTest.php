<?php

namespace Tests\Unit;

use App\Support\StoryFilter;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoryFilterTest extends TestCase
{
    #[Test]
    public function from_array_applies_defaults_for_empty_input(): void
    {
        $f = StoryFilter::fromArray([]);

        $this->assertSame('en', $f->language);
        $this->assertSame('all', $f->status);
        $this->assertSame('all', $f->category);
        $this->assertSame('', $f->text);
        $this->assertSame('all', $f->scope);
        $this->assertNull($f->authorId);
        $this->assertSame([], $f->tagIds);
        $this->assertSame('any', $f->dateField);
        $this->assertNull($f->windowStart);
        $this->assertSame('any', $f->hasImage);
        $this->assertSame('updated_at', $f->sortField);
        $this->assertSame('desc', $f->sortDir);
        $this->assertFalse($f->isDateActive());
    }

    #[Test]
    public function from_array_whitelists_unknown_values_back_to_defaults(): void
    {
        $f = StoryFilter::fromArray([
            'scope' => 'q; DROP TABLE stories',
            'hasImage' => 'maybe',
            'dateField' => 'yesterday',
            'dateMode' => 'forever',
            'sortField' => 'password_hash',
            'sortDir' => 'sideways',
            'language' => 'jp',
            'authorId' => 'not-a-number',
            'tagIds' => [3, 'not-num', '7'],
        ]);

        $this->assertSame('all', $f->scope);
        $this->assertSame('any', $f->hasImage);
        $this->assertSame('any', $f->dateField);
        $this->assertSame('updated_at', $f->sortField);
        $this->assertSame('desc', $f->sortDir);
        $this->assertSame('en', $f->language);
        $this->assertNull($f->authorId);
        $this->assertSame([3, 7], $f->tagIds);
    }

    #[Test]
    public function today_mode_computes_dhaka_day_window_in_utc(): void
    {
        // 14:30 Dhaka on 11 Oct === 08:30 UTC
        Carbon::setTestNow(Carbon::parse('2026-10-11 14:30', 'Asia/Dhaka'));

        $f = StoryFilter::fromArray(['dateField' => 'published', 'dateMode' => 'today']);

        $this->assertTrue($f->isDateActive());
        $this->assertSame('2026-10-11 00:00', $f->windowStart->timezone('Asia/Dhaka')->format('Y-m-d H:i'));
        // Inclusive day start .. exclusive next-day start (UTC)
        $this->assertSame('2026-10-10 18:00', $f->windowStart->timezone('UTC')->format('Y-m-d H:i'));
        $this->assertSame('2026-10-11 18:00', $f->windowEndExclusive->timezone('UTC')->format('Y-m-d H:i'));

        Carbon::setTestNow();
    }

    #[Test]
    public function range_mode_has_inclusive_start_and_exclusive_next_day_end(): void
    {
        $f = StoryFilter::fromArray([
            'dateField' => 'created',
            'dateMode' => 'range',
            'dateFrom' => '2026-10-01',
            'dateTo' => '2026-10-03',
        ]);

        $this->assertSame('2026-10-01 00:00', $f->windowStart->format('Y-m-d H:i'));
        $this->assertSame('2026-10-04 00:00', $f->windowEndExclusive->format('Y-m-d H:i'));
        // end-exclusive boundary is NOT included (correct range semantics)
        $this->assertFalse($f->windowEndExclusive->isSameDay(Carbon::parse('2026-10-03', 'Asia/Dhaka')));
    }

    #[Test]
    public function range_mode_treats_open_sides_and_garbage_dates_as_no_constraint(): void
    {
        $both = StoryFilter::fromArray(['dateField' => 'published', 'dateMode' => 'range', 'dateFrom' => '2026-10-01']);
        $this->assertNotNull($both->windowStart);
        $this->assertNull($both->windowEndExclusive);

        $garbage = StoryFilter::fromArray(['dateField' => 'published', 'dateMode' => 'range', 'dateFrom' => 'not-a-date']);
        $this->assertNull($garbage->windowStart);
        $this->assertNull($garbage->windowEndExclusive);
        $this->assertFalse($garbage->isDateActive());

        $fieldAny = StoryFilter::fromArray(['dateField' => 'any', 'dateMode' => 'today']);
        $this->assertFalse($fieldAny->isDateActive());
    }

    #[Test]
    public function scope_maps_meili_attributes_and_sql_columns(): void
    {
        $this->assertNull(StoryFilter::fromArray(['scope' => 'all'])->searchAttributes());
        $this->assertSame(['headline'], StoryFilter::fromArray(['scope' => 'headline'])->searchAttributes());
        $this->assertSame(['brief'], StoryFilter::fromArray(['scope' => 'brief'])->searchAttributes());
        $this->assertSame(['body_text'], StoryFilter::fromArray(['scope' => 'body'])->searchAttributes());

        $this->assertSame(['headline', 'brief', 'body_text'], StoryFilter::fromArray(['scope' => 'all'])->searchColumns());
        $this->assertSame(['body_text'], StoryFilter::fromArray(['scope' => 'body'])->searchColumns());
    }
}
