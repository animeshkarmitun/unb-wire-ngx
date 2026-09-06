<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $tops = [
            ['slug' => 'bangladesh', 'name_en' => 'Bangladesh', 'name_bn' => 'বাংলাদেশ', 'sort_order' => 1],
            ['slug' => 'world', 'name_en' => 'World', 'name_bn' => 'বিশ্ব', 'sort_order' => 2],
            ['slug' => 'sports', 'name_en' => 'Sports', 'name_bn' => 'খেলা', 'sort_order' => 3],
            ['slug' => 'business', 'name_en' => 'Business', 'name_bn' => 'বাণিজ্য', 'sort_order' => 4],
            ['slug' => 'opinion', 'name_en' => 'Opinion', 'name_bn' => 'মতামত', 'sort_order' => 5],
            ['slug' => 'lifestyle', 'name_en' => 'Lifestyle', 'name_bn' => 'লাইফস্টাইল', 'sort_order' => 6],
        ];

        $ids = [];
        foreach ($tops as $cat) {
            $existing = DB::table('categories')->where('slug', $cat['slug'])->first();
            if ($existing) {
                $ids[$cat['slug']] = $existing->id;
                DB::table('categories')->where('id', $existing->id)->update($cat);
            } else {
                $ids[$cat['slug']] = DB::table('categories')->insertGetId($cat);
            }
        }

        $subs = [
            ['slug' => 'district', 'name_en' => 'District', 'name_bn' => 'জেলা', 'parent' => 'bangladesh', 'sort_order' => 1],
            ['slug' => 'education', 'name_en' => 'Education', 'name_bn' => 'শিক্ষা', 'parent' => 'bangladesh', 'sort_order' => 2],
            ['slug' => 'law-and-order', 'name_en' => 'Law & Order', 'name_bn' => 'আইন ও শৃঙ্খলা', 'parent' => 'bangladesh', 'sort_order' => 3],
            ['slug' => 'cricket', 'name_en' => 'Cricket', 'name_bn' => 'ক্রিকেট', 'parent' => 'sports', 'sort_order' => 1],
            ['slug' => 'others', 'name_en' => 'Others', 'name_bn' => 'অন্যান্য', 'parent' => 'lifestyle', 'sort_order' => 1],
        ];

        foreach ($subs as $sub) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $sub['slug']],
                [
                    'name_en' => $sub['name_en'],
                    'name_bn' => $sub['name_bn'],
                    'parent_id' => $ids[$sub['parent']] ?? null,
                    'sort_order' => $sub['sort_order'],
                ]
            );
        }
    }
}
