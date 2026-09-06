<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\ClientUser;
use App\Models\Download;
use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\Package;
use App\Models\Role;
use App\Models\Story;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MediaSeeder extends Seeder
{
    public function run(): void
    {
        $uploaderRole = Role::where('name', 'Uploader-English')->first() ?? Role::first();
        $adminUser = User::first();

        // Photographers
        $photographerNames = [
            'Enamul Haque' => 'enamul@unbnews.org',
            'Sadia Rahman' => 'sadia@unbnews.org',
            'Rashed Sumon' => 'rashed@unbnews.org',
            'Tuhin Shubhra' => 'tuhin@unbnews.org',
            'Mahmud Hossain' => 'mahmud@unbnews.org',
            'Mim Akter' => 'mim@unbnews.org',
            'Sharif Uddin' => 'sharif@unbnews.org',
        ];

        $photographers = [];
        foreach ($photographerNames as $name => $email) {
            $photographers[$name] = User::firstOrCreate(
                ['email' => $email],
                [
                    'public_id' => (string) Str::ulid(),
                    'name' => $name,
                    'password' => bcrypt('password'),
                    'role_id' => $uploaderRole?->id,
                ]
            );
        }

        $cats = Category::pluck('id', 'slug')->toArray();
        $stdPkg = Package::where('code', 'STANDARD-NEWS')->first();
        $excPkg = Package::where('code', 'PREMIUM-BUNDLE')->first();

        // 1. Seed Library Assets
        $assetsData = [
            [
                'title' => 'Rizvi waves to activists outside BNP central office',
                'caption' => "Rizvi waves to activists outside BNP's Nayapaltan central office",
                'by' => 'Enamul Haque',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g1',
                'status' => 'library',
                'approved' => true,
                'pkg' => $excPkg,
                'dl' => 41,
                'kw' => ['bnp', 'rizvi', 'nayapaltan'],
                'story_title' => "Rizvi returns to BNP's Nayapaltan office",
            ],
            [
                'title' => 'Rizvi waves to activists — burst series',
                'caption' => 'Rizvi waves to activists — burst series',
                'by' => 'Enamul Haque',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g2',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 18,
                'kw' => ['bnp', 'rizvi'],
                'stack' => 9,
                'story_title' => "Rizvi returns to BNP's Nayapaltan office",
            ],
            [
                'title' => 'A customer scans Bangla QR at New Market',
                'caption' => 'A customer scans Bangla QR at a New Market shop',
                'by' => 'Sadia Rahman',
                'loc' => 'Dhaka',
                'cat' => 'business',
                'r' => 1.33,
                'grad' => 'g4',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 33,
                'kw' => ['bangla qr', 'fintech', 'payment'],
                'story_title' => 'Bangla QR daily transactions cross Tk 110 crore',
            ],
            [
                'title' => 'Morning fog settles over Buriganga river',
                'caption' => 'Morning fog settles over the Buriganga river',
                'by' => 'Rashed Sumon',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.78,
                'grad' => 'g5',
                'status' => 'field',
                'approved' => false,
                'pkg' => null,
                'dl' => 0,
                'kw' => ['buriganga', 'fog', 'winter'],
            ],
            [
                'title' => 'Tigers fielding drills at Sher-e-Bangla stadium',
                'caption' => 'Tigers fielding drills at Sher-e-Bangla stadium',
                'by' => 'Tuhin Shubhra',
                'loc' => 'Mirpur, Dhaka',
                'cat' => 'sports',
                'r' => 1.5,
                'grad' => 'g3',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 12,
                'kw' => ['cricket', 'tigers', 'practice'],
                'stack' => 14,
            ],
            [
                'title' => 'Fishermen unload morning hilsa catch',
                'caption' => 'Fishermen unload the morning hilsa catch',
                'by' => 'Mahmud Hossain',
                'loc' => 'Chandpur',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g6',
                'status' => 'field',
                'approved' => false,
                'pkg' => null,
                'dl' => 0,
                'kw' => ['hilsa', 'fish', 'chandpur'],
            ],
            [
                'title' => 'Trading screens glow at Dhaka Stock Exchange',
                'caption' => 'Trading screens glow at the Dhaka Stock Exchange',
                'by' => 'Sadia Rahman',
                'loc' => 'Motijheel, Dhaka',
                'cat' => 'business',
                'r' => 1.33,
                'grad' => 'g7',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 8,
                'kw' => ['dse', 'stocks', 'market'],
            ],
            [
                'title' => 'Newly planted trees line Bishkhali riverbank',
                'caption' => 'Newly planted trees line the Bishkhali riverbank',
                'by' => 'Rashed Sumon',
                'loc' => 'Bagerhat',
                'cat' => 'bangladesh',
                'r' => 1.78,
                'grad' => 'g8',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 22,
                'kw' => ['environment', 'river', 'trees'],
                'story_title' => 'New greenery on the Bishkhali riverbanks',
            ],
            [
                'title' => 'Containers stacked high at Chattogram port',
                'caption' => 'Containers stacked high at Chattogram port',
                'by' => 'Mahmud Hossain',
                'loc' => 'Chattogram',
                'cat' => 'business',
                'r' => 1.5,
                'grad' => 'g1',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 15,
                'kw' => ['port', 'export', 'trade'],
            ],
            [
                'title' => 'President places wreath at National Memorial',
                'caption' => 'The president places a wreath at the National Memorial',
                'by' => 'Enamul Haque',
                'loc' => 'Savar',
                'cat' => 'bangladesh',
                'r' => 1.33,
                'grad' => 'g2',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 28,
                'kw' => ['president', 'memorial', 'savar'],
                'story_title' => 'President pays homage at National Memorial',
            ],
            [
                'title' => 'Boy wades through flooded street after rain',
                'caption' => 'A boy wades through a flooded street after heavy rain',
                'by' => 'Rashed Sumon',
                'loc' => 'Sylhet',
                'cat' => 'bangladesh',
                'r' => 0.75,
                'grad' => 'g3',
                'status' => 'library',
                'approved' => true,
                'embargo' => now()->addHours(4),
                'pkg' => $excPkg,
                'dl' => 0,
                'kw' => ['flood', 'sylhet', 'monsoon'],
            ],
            [
                'title' => 'Commuters crowd launch terminal at Sadarghat',
                'caption' => 'Commuters crowd the launch terminal at Sadarghat',
                'by' => 'Mahmud Hossain',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g4',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 6,
                'kw' => ['sadarghat', 'launch', 'commute'],
            ],
            [
                'title' => 'PM arrives for press conference pool frame',
                'caption' => 'PM arrives for the press conference — pool frame',
                'by' => 'Enamul Haque',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g5',
                'status' => 'library',
                'approved' => true,
                'embargo' => now()->addHours(4),
                'pkg' => $excPkg,
                'dl' => 0,
                'kw' => ['pm', 'press conference'],
            ],
            [
                'title' => 'Garment workers on factory floor in Ashulia',
                'caption' => 'Garment workers on a factory floor in Ashulia',
                'by' => 'Sadia Rahman',
                'loc' => 'Ashulia',
                'cat' => 'business',
                'r' => 1.78,
                'grad' => 'g6',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 9,
                'kw' => ['rmg', 'garment', 'factory'],
                'stack' => 6,
            ],
            [
                'title' => 'Election Commission briefing ahead of polls',
                'caption' => 'Election Commission briefing ahead of city polls',
                'by' => 'Enamul Haque',
                'loc' => 'Agargaon, Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g7',
                'status' => 'field',
                'approved' => false,
                'pkg' => null,
                'dl' => 0,
                'kw' => ['ec', 'election', 'polls'],
            ],
            [
                'title' => 'Shakib Al Hasan bowls during training session',
                'caption' => 'Shakib Al Hasan bowls during a training session',
                'by' => 'Tuhin Shubhra',
                'loc' => 'Mirpur, Dhaka',
                'cat' => 'sports',
                'r' => 1.33,
                'grad' => 'g8',
                'status' => 'field',
                'approved' => false,
                'pkg' => null,
                'dl' => 0,
                'kw' => ['shakib', 'cricket', 'practice'],
            ],
            [
                'title' => 'Street vendors open shop at Karwan Bazar',
                'caption' => 'Street vendors open shop at Karwan Bazar at dawn',
                'by' => 'Mahmud Hossain',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g1',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 11,
                'kw' => ['karwan bazar', 'morning', 'vendors'],
            ],
            [
                'title' => 'Bangladesh women celebrate wicket in T20',
                'caption' => 'Bangladesh women celebrate a wicket in the T20 series',
                'by' => 'Tuhin Shubhra',
                'loc' => 'Sylhet',
                'cat' => 'sports',
                'r' => 1.78,
                'grad' => 'g2',
                'status' => 'library',
                'approved' => true,
                'pkg' => $excPkg,
                'dl' => 36,
                'kw' => ['women cricket', 't20', 'sylhet'],
                'story_title' => 'Women clinch T20 series in Sylhet',
            ],
            [
                'title' => 'Padma Bridge aerial view morning traffic',
                'caption' => 'Padma Bridge aerial view — morning traffic',
                'by' => 'Rashed Sumon',
                'loc' => 'Mawa',
                'cat' => 'bangladesh',
                'r' => 1.78,
                'grad' => 'g3',
                'status' => 'library',
                'approved' => true,
                'pkg' => $stdPkg,
                'dl' => 19,
                'kw' => ['padma bridge', 'aerial'],
                'kind' => 'video',
                'duration_ms' => 48000,
            ],
            [
                'title' => 'Smog hangs over Dhaka skyline at dawn',
                'caption' => 'Smog hangs over the Dhaka skyline at dawn',
                'by' => 'Mahmud Hossain',
                'loc' => 'Dhaka',
                'cat' => 'bangladesh',
                'r' => 1.5,
                'grad' => 'g4',
                'status' => 'field',
                'approved' => false,
                'pkg' => null,
                'dl' => 0,
                'kw' => ['smog', 'pollution', 'dhaka'],
            ],
        ];

        foreach ($assetsData as $d) {
            $user = $photographers[$d['by']] ?? $adminUser;
            $catId = $cats[$d['cat']] ?? ($cats['bangladesh'] ?? null);

            $asset = MediaAsset::create([
                'public_id' => (string) Str::ulid(),
                'kind' => $d['kind'] ?? 'photo',
                'status' => $d['status'],
                'title' => $d['title'],
                'caption' => $d['caption'],
                'credit_line' => 'Photo: '.$d['by'].' / UNB',
                'photographer_id' => $user?->id,
                'source' => 'staff',
                'category_id' => $catId,
                'location_city' => $d['loc'],
                'location_country' => 'Bangladesh',
                'captured_at' => now()->subDays(2),
                'en_tags' => $d['kw'],
                'width' => 1200,
                'height' => (int) round(1200 / ($d['r'] ?? 1.5)),
                'duration_ms' => $d['duration_ms'] ?? null,
                'mime' => ($d['kind'] ?? 'photo') === 'video' ? 'video/mp4' : 'image/jpeg',
                'size_bytes' => 245000,
                'checksum' => hash('sha256', $d['title']),
                'storage_disk' => 'public',
                'original_path' => 'media/demo/'.Str::slug($d['title']).'.jpg',
                'derivatives' => [
                    'grad' => $d['grad'] ?? 'g1',
                    'r' => $d['r'] ?? 1.5,
                    'stack' => $d['stack'] ?? null,
                ],
                'embargo_until' => $d['embargo'] ?? null,
                'uploaded_by' => $user?->id ?? $adminUser->id,
                'approved_by' => ($d['approved'] ?? false) ? $adminUser->id : null,
                'approved_at' => ($d['approved'] ?? false) ? now()->subDay() : null,
                'download_count' => $d['dl'] ?? 0,
                'created_at' => now()->subDays(rand(1, 4)),
            ]);

            if ($d['pkg'] ?? null) {
                DB::table('package_media')->updateOrInsert(
                    ['package_id' => $d['pkg']->id, 'asset_id' => $asset->id],
                    ['added_at' => now()]
                );
            }

            if (! empty($d['story_title'])) {
                $story = Story::where('headline', 'like', '%'.substr($d['story_title'], 0, 15).'%')->first();
                if (! $story) {
                    $story = Story::create([
                        'public_id' => (string) Str::ulid(),
                        'language' => 'en',
                        'status' => 'published',
                        'headline' => $d['story_title'],
                        'brief' => $d['caption'],
                        'body_html' => '<p>'.$d['caption'].'</p>',
                        'body_text' => $d['caption'],
                        'category_id' => $catId,
                        'published_at' => now()->subDays(1),
                        'owner_id' => $adminUser->id,
                        'created_by' => $adminUser->id,
                    ]);
                }
                DB::table('story_media')->updateOrInsert(
                    ['story_id' => $story->id, 'asset_id' => $asset->id],
                    ['role' => 'featured', 'sort_order' => 1]
                );
            }
        }

        // 2. Seed Field Intake Batches
        $fieldBatches = [
            [
                'event_label' => "Nor'wester aftermath — city damage",
                'by' => 'Rashed Sumon',
                'urgency' => 'urgent',
                'loc' => 'DHAKA',
                'cat' => 'bangladesh',
                'wait' => 12,
                'frames' => [
                    [
                        'cap' => "A huge rain tree lies uprooted across a Dhanmondi road after the overnight nor'wester",
                        'kw' => ['norwester', 'storm', 'damage', 'dhaka'],
                        'r' => 1.5,
                        'grad' => 'g6',
                    ],
                    [
                        'cap' => 'Commuters wade through knee-deep water at Green Road intersection',
                        'kw' => ['waterlogging', 'rain', 'commuters'],
                        'r' => 1.33,
                        'grad' => 'g1',
                    ],
                    [
                        'cap' => 'Rickshaw-puller Karim Mia surveys his battered roadside tea stall',
                        'kw' => ['portrait', 'storm', 'livelihood'],
                        'r' => 0.8,
                        'grad' => 'g7',
                    ],
                ],
            ],
            [
                'event_label' => 'Hilsa landing at Chandpur',
                'by' => 'Mim Akter',
                'urgency' => 'routine',
                'loc' => 'CHANDPUR',
                'cat' => 'bangladesh',
                'wait' => 38,
                'frames' => [
                    [
                        'cap' => 'Fishermen carry crates of fresh hilsa from boats at Chandpur landing station',
                        'kw' => ['hilsa', 'fish', 'chandpur', 'river'],
                        'r' => 1.5,
                        'grad' => 'g3',
                    ],
                    [
                        'cap' => 'Traders bid at the morning hilsa auction, prices down after a big catch',
                        'kw' => ['hilsa', 'auction', 'market'],
                        'r' => 1.78,
                        'grad' => 'g4',
                    ],
                    [
                        'cap' => 'A worker weighs hilsa before loading onto ice-filled trucks for Dhaka',
                        'kw' => ['hilsa', 'transport', 'ice'],
                        'r' => 1.5,
                        'grad' => 'g8',
                    ],
                ],
            ],
            [
                'event_label' => 'Tigers training, Sher-e-Bangla',
                'by' => 'Sharif Uddin',
                'urgency' => 'routine',
                'loc' => 'MIRPUR',
                'cat' => 'sports',
                'wait' => 60,
                'frames' => [
                    [
                        'cap' => 'Shakib Al Hasan bowls in the nets during Tigers training session',
                        'kw' => ['shakib', 'cricket', 'nets', 'practice'],
                        'r' => 1.5,
                        'grad' => 'g5',
                    ],
                    [
                        'cap' => 'Fielding coach demonstrates a diving drill to the squad',
                        'kw' => ['cricket', 'fielding', 'training'],
                        'r' => 1.78,
                        'grad' => 'g2',
                    ],
                ],
            ],
        ];

        foreach ($fieldBatches as $fb) {
            $user = $photographers[$fb['by']] ?? $adminUser;
            $batch = MediaBatch::create([
                'public_id' => (string) Str::ulid(),
                'uploader_id' => $user->id,
                'event_label' => $fb['event_label'],
                'urgency' => $fb['urgency'],
                'status' => 'pending',
                'submitted_at' => now()->subMinutes($fb['wait']),
            ]);

            $catId = $cats[$fb['cat']] ?? ($cats['bangladesh'] ?? null);

            foreach ($fb['frames'] as $frame) {
                MediaAsset::create([
                    'public_id' => (string) Str::ulid(),
                    'kind' => 'photo',
                    'status' => 'field',
                    'batch_id' => $batch->id,
                    'title' => Str::limit($frame['cap'], 60),
                    'caption' => $frame['cap'],
                    'credit_line' => 'Photo: '.$fb['by'].' / UNB',
                    'photographer_id' => $user->id,
                    'source' => 'field',
                    'category_id' => $catId,
                    'event_label' => $fb['event_label'],
                    'location_city' => $fb['loc'],
                    'location_country' => 'Bangladesh',
                    'captured_at' => now()->subMinutes($fb['wait'] + 10),
                    'en_tags' => $frame['kw'],
                    'width' => 1200,
                    'height' => (int) round(1200 / ($frame['r'] ?? 1.5)),
                    'mime' => 'image/jpeg',
                    'size_bytes' => 180000,
                    'checksum' => hash('sha256', $frame['cap']),
                    'storage_disk' => 'public',
                    'original_path' => 'media/field/'.Str::slug($batch->event_label).'.jpg',
                    'derivatives' => [
                        'grad' => $frame['grad'],
                        'r' => $frame['r'],
                    ],
                    'uploaded_by' => $user->id,
                    'download_count' => 0,
                    'created_at' => now()->subMinutes($fb['wait']),
                ]);
            }
        }

        // 3. Seed AP Wire Photos (matching app-data/ap-photo-manager.html)
        $apPhotos = [
            [
                'cap' => 'Zelenskyy speaks during a press conference in Kyiv',
                'cat' => 'world',
                'sub' => 'Europe',
                'grad' => 'g1',
                'date' => '2026-08-23 15:30:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 42,
                'loc' => 'Kyiv',
                'country' => 'Ukraine',
                'extra' => false,
            ],
            [
                'cap' => "Pope Leo greets the crowd at St. Peter's Square",
                'cat' => 'world',
                'sub' => 'Europe',
                'grad' => 'g4',
                'date' => '2026-08-23 11:20:00',
                'width' => 2800,
                'height' => 1867,
                'dl' => 31,
                'loc' => 'Vatican City',
                'country' => 'Vatican',
                'extra' => false,
            ],
            [
                'cap' => 'Ukrainian flag flies over a government building in Kyiv',
                'cat' => 'world',
                'sub' => 'Europe',
                'grad' => 'g6',
                'date' => '2026-08-22 17:45:00',
                'width' => 3200,
                'height' => 2133,
                'dl' => 27,
                'loc' => 'Kyiv',
                'country' => 'Ukraine',
                'extra' => false,
            ],
            [
                'cap' => 'Military commanders arrive for talks in Moscow',
                'cat' => 'world',
                'sub' => 'Europe',
                'grad' => 'g8',
                'date' => '2026-08-22 14:10:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 19,
                'loc' => 'Moscow',
                'country' => 'Russia',
                'extra' => false,
            ],
            [
                'cap' => 'Bangladesh players celebrate a wicket at Mirpur',
                'cat' => 'sports',
                'sub' => 'Asia',
                'grad' => 'g3',
                'date' => '2026-08-23 18:00:00',
                'width' => 3500,
                'height' => 2333,
                'dl' => 88,
                'loc' => 'Dhaka',
                'country' => 'Bangladesh',
                'extra' => false,
            ],
            [
                'cap' => 'Jatiya Sangsad in session in Dhaka',
                'cat' => 'politics',
                'sub' => 'Asia',
                'grad' => 'g5',
                'date' => '2026-08-22 16:30:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 24,
                'loc' => 'Dhaka',
                'country' => 'Bangladesh',
                'extra' => false,
            ],
            [
                'cap' => 'Stocks rally on the trading floor at DSE',
                'cat' => 'business',
                'sub' => 'Asia',
                'grad' => 'g2',
                'date' => '2026-08-22 12:15:00',
                'width' => 2900,
                'height' => 1933,
                'dl' => 12,
                'loc' => 'Dhaka',
                'country' => 'Bangladesh',
                'extra' => false,
            ],
            [
                'cap' => 'Flooded street in Chattogram after monsoon rain',
                'cat' => 'weather',
                'sub' => 'Asia',
                'grad' => 'g6',
                'date' => '2026-08-21 09:40:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 36,
                'loc' => 'Chattogram',
                'country' => 'Bangladesh',
                'extra' => false,
            ],
            [
                'cap' => 'New AI data centre opens in Singapore',
                'cat' => 'tech',
                'sub' => 'Asia',
                'grad' => 'g1',
                'date' => '2026-08-21 13:50:00',
                'width' => 3100,
                'height' => 2067,
                'dl' => 15,
                'loc' => 'Singapore',
                'country' => 'Singapore',
                'extra' => false,
            ],
            [
                'cap' => 'Premiere night at a Kolkata film festival',
                'cat' => 'entertainment',
                'sub' => 'Asia',
                'grad' => 'g7',
                'date' => '2026-08-21 20:00:00',
                'width' => 2800,
                'height' => 1867,
                'dl' => 22,
                'loc' => 'Kolkata',
                'country' => 'India',
                'extra' => false,
            ],
            [
                'cap' => 'Nurses run a vaccine drive in a rural district',
                'cat' => 'health',
                'sub' => 'Asia',
                'grad' => 'g3',
                'date' => '2026-08-20 10:15:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 18,
                'loc' => 'Bogura',
                'country' => 'Bangladesh',
                'extra' => false,
            ],
            [
                'cap' => 'Mangrove saplings planted in the Sundarbans',
                'cat' => 'environment',
                'sub' => 'Asia',
                'grad' => 'g3',
                'date' => '2026-08-20 14:20:00',
                'width' => 3300,
                'height' => 2200,
                'dl' => 29,
                'loc' => 'Khulna',
                'country' => 'Bangladesh',
                'extra' => true,
            ],
            [
                'cap' => 'Cyclone shelter drill in a coastal village',
                'cat' => 'weather',
                'sub' => 'Asia',
                'grad' => 'g4',
                'date' => '2026-08-20 08:30:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 11,
                'loc' => "Cox's Bazar",
                'country' => 'Bangladesh',
                'extra' => true,
            ],
            [
                'cap' => 'Crew boards lunar mission module at launch site',
                'cat' => 'science',
                'sub' => 'Americas',
                'grad' => 'g8',
                'date' => '2026-08-19 22:45:00',
                'width' => 3600,
                'height' => 2400,
                'dl' => 47,
                'loc' => 'Cape Canaveral',
                'country' => 'United States',
                'extra' => true,
            ],
            [
                'cap' => 'Tea garden workers in the Sylhet hills',
                'cat' => 'lifestyle',
                'sub' => 'Asia',
                'grad' => 'g3',
                'date' => '2026-08-19 11:00:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 20,
                'loc' => 'Sylhet',
                'country' => 'Bangladesh',
                'extra' => true,
            ],
            [
                'cap' => 'Election commission briefing in Dhaka',
                'cat' => 'election',
                'sub' => 'Asia',
                'grad' => 'g2',
                'date' => '2026-08-19 16:15:00',
                'width' => 3000,
                'height' => 2000,
                'dl' => 33,
                'loc' => 'Dhaka',
                'country' => 'Bangladesh',
                'extra' => true,
            ],
        ];

        foreach ($apPhotos as $ap) {
            $catId = $cats[$ap['cat']] ?? null;

            MediaAsset::create([
                'public_id' => (string) Str::ulid(),
                'kind' => 'photo',
                'status' => 'library',
                'batch_id' => null,
                'title' => Str::limit($ap['cap'], 60),
                'caption' => $ap['cap'],
                'credit_line' => 'AP Photo',
                'photographer_id' => null,
                'source' => 'ap',
                'category_id' => $catId,
                'event_label' => $ap['cat'],
                'location_city' => $ap['loc'],
                'location_country' => $ap['country'],
                'captured_at' => $ap['date'],
                'en_tags' => [$ap['cat'], $ap['sub']],
                'width' => $ap['width'],
                'height' => $ap['height'],
                'mime' => 'image/jpeg',
                'size_bytes' => 450000,
                'checksum' => hash('sha256', $ap['cap'].$ap['date']),
                'storage_disk' => 'public',
                'original_path' => 'media/ap/'.Str::slug(Str::limit($ap['cap'], 30)).'.jpg',
                'derivatives' => [
                    'grad' => $ap['grad'],
                    'ap_category' => $ap['cat'],
                    'sub_category' => $ap['sub'],
                    'dim' => $ap['width'].' × '.$ap['height'],
                    'extra' => $ap['extra'],
                ],
                'uploaded_by' => $adminUser->id,
                'download_count' => $ap['dl'],
                'created_at' => $ap['date'],
            ]);
        }

        // 2. Seed Download & License Audit Logs for The Daily Star
        $dailyStar = Client::where('code', 'DST')->first();
        if ($dailyStar) {
            Download::where('client_id', $dailyStar->id)->delete();

            $clientRoleId = Role::where('type', 'client')->value('id') ?? Role::first()?->id ?? 1;

            $photoUser = ClientUser::where('client_id', $dailyStar->id)->where('email', 'like', '%photo%')->first()
                ?? ClientUser::firstOrCreate(
                    ['client_id' => $dailyStar->id, 'email' => 'photo@dailystar.com'],
                    ['name' => 'Photo Desk', 'password' => bcrypt('password'), 'client_role_id' => $clientRoleId, 'status' => 'active']
                );

            $newsUser = ClientUser::where('client_id', $dailyStar->id)->where('email', 'like', '%news%')->first()
                ?? ClientUser::firstOrCreate(
                    ['client_id' => $dailyStar->id, 'email' => 'newsdesk@dailystar.com'],
                    ['name' => 'News Desk', 'password' => bcrypt('password'), 'client_role_id' => $clientRoleId, 'status' => 'active']
                );

            $sportsUser = ClientUser::where('client_id', $dailyStar->id)->where('email', 'like', '%sports%')->first()
                ?? ClientUser::firstOrCreate(
                    ['client_id' => $dailyStar->id, 'email' => 'sports@dailystar.com'],
                    ['name' => 'Sports Desk', 'password' => bcrypt('password'), 'client_role_id' => $clientRoleId, 'status' => 'active']
                );

            $mRizvi = MediaAsset::where('title', 'like', '%Rizvi%')->first() ?? MediaAsset::first();
            $mAp1 = MediaAsset::where('source', 'ap')->first() ?? MediaAsset::first();
            $mAp2 = MediaAsset::where('source', 'ap')->skip(1)->first() ?? MediaAsset::first();
            $mTigers = MediaAsset::where('title', 'like', '%Tigers%')->orWhere('title', 'like', '%cricket%')->first() ?? MediaAsset::first();
            $mPadma = MediaAsset::where('title', 'like', '%Padma%')->orWhere('kind', 'video')->first() ?? MediaAsset::first();

            $downloadsData = [
                [
                    'client_id' => $dailyStar->id,
                    'client_user_id' => $photoUser->id,
                    'item_type' => 'media',
                    'item_id' => $mRizvi?->id ?? 1,
                    'format' => 'JPEG',
                    'size_bytes' => 524288,
                    'ip' => '103.118.19.26',
                    'created_at' => Carbon::parse('2026-08-24 09:32:00'),
                ],
                [
                    'client_id' => $dailyStar->id,
                    'client_user_id' => $photoUser->id,
                    'item_type' => 'media',
                    'item_id' => $mAp1?->id ?? 1,
                    'format' => 'JPEG',
                    'size_bytes' => 450000,
                    'ip' => '103.118.19.26',
                    'created_at' => Carbon::parse('2026-08-23 20:15:00'),
                ],
                [
                    'client_id' => $dailyStar->id,
                    'client_user_id' => $newsUser->id,
                    'item_type' => 'media',
                    'item_id' => $mAp2?->id ?? 1,
                    'format' => 'JPEG',
                    'size_bytes' => 480000,
                    'ip' => '103.118.19.26',
                    'created_at' => Carbon::parse('2026-08-23 18:41:00'),
                ],
                [
                    'client_id' => $dailyStar->id,
                    'client_user_id' => $sportsUser->id,
                    'item_type' => 'media',
                    'item_id' => $mTigers?->id ?? 1,
                    'format' => 'JPEG',
                    'size_bytes' => 610000,
                    'ip' => '103.118.19.26',
                    'created_at' => Carbon::parse('2026-08-22 17:20:00'),
                ],
                [
                    'client_id' => $dailyStar->id,
                    'client_user_id' => null, // video desk (FTP auto-push)
                    'item_type' => 'media',
                    'item_id' => $mPadma?->id ?? 1,
                    'format' => 'MP4',
                    'size_bytes' => 12582912,
                    'ip' => '103.118.19.26',
                    'created_at' => Carbon::parse('2026-08-22 11:05:00'),
                ],
            ];

            foreach ($downloadsData as $d) {
                Download::create($d);
            }
        }
    }
}
