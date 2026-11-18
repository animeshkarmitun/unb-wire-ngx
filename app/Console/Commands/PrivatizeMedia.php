<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * COS-74: moves every media file off the web-served public disk
 * (storage/app/public, exposed at /storage via the public/storage link)
 * onto the private local disk (storage/app/private, never web-served).
 *
 * Run on the live server during deploy: `php artisan media:privatize`
 */
class PrivatizeMedia extends Command
{
    protected $signature = 'media:privatize {--dry-run : Report what would be moved without changing anything}';

    protected $description = 'Move media originals/derivatives (and orphans under media/) from the public disk to the private local disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $assets = 0;
        $files = 0;
        $orphans = 0;

        $rows = MediaAsset::withTrashed()->where('storage_disk', 'public')->get();
        foreach ($rows as $asset) {
            $paths = array_filter(array_merge(
                [$asset->original_path],
                array_map(
                    fn ($d) => is_string($d) ? $d : ($d['path'] ?? null),
                    $asset->derivatives ?? []
                )
            ));

            foreach ($paths as $path) {
                if (! Storage::disk('public')->exists($path)) {
                    continue;
                }
                if (! $dryRun) {
                    $this->moveToPrivate($path);
                }
                $files++;
            }

            if (! $dryRun) {
                $asset->forceFill(['storage_disk' => 'local'])->saveQuietly();
            }
            $assets++;
        }

        // Orphan sweep: anything left under media/ on the public disk that no
        // DB row references (e.g. manually placed AP wire drops).
        foreach (Storage::disk('public')->allFiles('media') as $path) {
            if (! $dryRun) {
                $this->moveToPrivate($path);
            }
            $orphans++;
        }

        $this->info($dryRun ? '[dry-run] ' : ''."Privatized {$assets} media assets ({$files} files), moved {$orphans} orphan files under media/.");
        if ($dryRun && ($files > 0 || $orphans > 0)) {
            $this->line('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    private function moveToPrivate(string $path): void
    {
        $stream = Storage::disk('public')->readStream($path);
        try {
            Storage::disk('local')->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        Storage::disk('public')->delete($path);
    }
}
