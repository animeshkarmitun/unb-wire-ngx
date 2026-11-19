<?php

namespace App\Console\Commands;

use App\Models\Story;
use App\Support\BriefBuilder;
use Illuminate\Console\Command;

/**
 * COS-78: repairs stored briefs that were cut from the body at a fixed
 * length (mid-word cuts, stray \r\n). Only touches briefs that carry the
 * artefacts: newlines, or an exact fixed-length cut.
 */
class RepairStoryBriefs extends Command
{
    protected $signature = 'stories:repair-briefs {--dry-run : Report what would change without saving}';

    protected $description = 'Normalise whitespace and re-trim auto-generated story briefs on sentence/word boundaries';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixed = 0;
        $checked = 0;

        Story::whereNotNull('brief')->where('brief', '!=', '')->chunkById(200, function ($stories) use (&$fixed, &$checked, $dryRun) {
            foreach ($stories as $story) {
                $brief = $story->brief;
                $checked++;

                $hasArtefacts = preg_match('/\r|\n/u', $brief) === 1 || mb_strlen($brief) >= 280;
                if (! $hasArtefacts) {
                    continue;
                }

                $clean = BriefBuilder::fromPlainText($brief);

                // A brief that still doesn't end with sentence punctuation is a
                // partial cut — force the sentence/word-boundary cut path.
                if (! preg_match('/[.!?…।]$/u', $clean)) {
                    $clean = BriefBuilder::fromPlainText($clean, mb_strlen($clean) - 1);
                }

                if ($clean === $brief) {
                    continue;
                }

                if (! $dryRun) {
                    $story->newQuery()->whereKey($story->getKey())->update(['brief' => $clean]);
                }
                $fixed++;
            }
        });

        $this->info(($dryRun ? '[dry-run] ' : '')."Checked {$checked} briefs, repaired {$fixed}.");

        return self::SUCCESS;
    }
}
