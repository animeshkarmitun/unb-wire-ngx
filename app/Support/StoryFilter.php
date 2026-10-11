<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonTimeZone;

/**
 * Validated value object for the news-list filter set (structured + text + sort).
 * Built from untrusted Livewire state — every dimension is whitelisted, unknown
 * values fall back to defaults so engine-facing strings are never attacker-shaped.
 */
class StoryFilter
{
    public const SCOPES = ['all', 'headline', 'brief', 'body', 'caption'];

    public const DATE_FIELDS = ['any', 'published', 'created'];

    public const DATE_MODES = ['any', 'today', 'range'];

    public const HAS_IMAGE = ['any', 'with', 'without'];

    private const SCOPE_ATTRIBUTES = [
        'headline' => ['headline'],
        'brief' => ['brief'],
        'body' => ['body_text'],
    ];

    private const SCOPE_COLUMNS = [
        'headline' => ['headline'],
        'brief' => ['brief'],
        'body' => ['body_text'],
        'all' => ['headline', 'brief', 'body_text'],
    ];

    /** Asia/Dhaka has no DST; fixed offset is safe for day-boundary math. */
    private const NEWSROOM_TZ = 'Asia/Dhaka';

    private function __construct(
        public readonly string $language,
        public readonly string $status,
        public readonly string $category,
        public readonly string $text,
        public readonly string $scope,
        public readonly mixed $authorId,
        public readonly array $tagIds,
        public readonly string $dateField,
        public readonly ?CarbonImmutable $windowStart,
        public readonly ?CarbonImmutable $windowEndExclusive,
        public readonly string $hasImage,
        public readonly string $sortField,
        public readonly string $sortDir,
    ) {}

    public static function fromArray(array $a): self
    {
        $scopeRaw = $a['scope'] ?? 'all';
        $dateFieldRaw = $a['dateField'] ?? 'any';
        $dateModeRaw = $a['dateMode'] ?? 'any';
        $hasImageRaw = $a['hasImage'] ?? 'any';
        $sortFieldRaw = $a['sortField'] ?? 'updated_at';

        $scope = in_array($scopeRaw, self::SCOPES, true) ? $scopeRaw : 'all';
        $dateField = in_array($dateFieldRaw, self::DATE_FIELDS, true) ? $dateFieldRaw : 'any';
        $dateMode = in_array($dateModeRaw, self::DATE_MODES, true) ? $dateModeRaw : 'any';
        $hasImage = in_array($hasImageRaw, self::HAS_IMAGE, true) ? $hasImageRaw : 'any';
        $sortField = in_array($sortFieldRaw, ['title', 'category', 'published', 'views', 'status'], true)
            ? $sortFieldRaw : 'updated_at';
        $sortDir = (($a['sortDir'] ?? 'desc') === 'asc') ? 'asc' : 'desc';

        [$windowStart, $windowEnd] = self::window($dateField, $dateMode, $a['dateFrom'] ?? '', $a['dateTo'] ?? '');

        return new self(
            language: ($a['language'] ?? 'en') === 'bn' ? 'bn' : 'en',
            status: (string) ($a['status'] ?? 'all'),
            category: (string) ($a['category'] ?? 'all'),
            text: trim((string) ($a['text'] ?? '')),
            scope: $scope,
            authorId: is_numeric($a['authorId'] ?? null) ? (int) $a['authorId'] : null,
            tagIds: array_values(array_map('intval', array_values(array_filter((array) ($a['tagIds'] ?? []), 'is_numeric')))),
            dateField: $dateField,
            windowStart: $windowStart,
            windowEndExclusive: $windowEnd,
            hasImage: $hasImage,
            sortField: $sortField,
            sortDir: $sortDir,
        );
    }

    public function isDateActive(): bool
    {
        return $this->dateField !== 'any' && $this->windowStart !== null;
    }

    /**
     * Meilisearch staff index is scoped per-query on these attributes.
     *
     * @return list<string>|null null = no restriction (default searchable set)
     */
    public function searchAttributes(): ?array
    {
        return self::SCOPE_ATTRIBUTES[$this->scope] ?? null;
    }

    /** @return list<string> SQL fallback ILIKE columns for the active scope */
    public function searchColumns(): array
    {
        return self::SCOPE_COLUMNS[$this->scope] ?? self::SCOPE_COLUMNS['all'];
    }

    /**
     * Inclusive start + exclusive end in UTC for the newsroom-day window.
     *
     * @param  string  $from  Y-m-d
     * @param  string  $to  Y-m-d inclusive end date
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private static function window(string $dateField, string $dateMode, string $from, string $to): array
    {
        if ($dateField === 'any') {
            return [null, null];
        }

        $tz = CarbonTimeZone::create(self::NEWSROOM_TZ);

        try {
            if ($dateMode === 'today') {
                $start = CarbonImmutable::now($tz)->startOfDay();

                return [$start, $start->addDay()];
            }

            if ($dateMode !== 'range') {
                return [null, null];
            }

            $start = $from !== '' ? self::dayStart($from, $tz) : null;
            $end = $to !== '' ? self::dayStart($to, $tz)?->addDay() : null;

            return [$start, $end];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    private static function dayStart(string $ymd, CarbonTimeZone $tz): ?CarbonImmutable
    {
        $dt = CarbonImmutable::createFromFormat('Y-m-d', $ymd, $tz);

        return $dt === false ? null : $dt->startOfDay();
    }
}
