<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Story;
use App\Models\Tag;
use App\Services\Delivery\WireFormatFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WireFormatTest extends TestCase
{
    use RefreshDatabase;

    private Story $story;

    protected function setUp(): void
    {
        parent::setUp();

        $category = new Category(['name_en' => 'Test Category', 'name_bn' => 'Test Category BN', 'is_active' => true]);
        $category->slug = 'test-category';
        $category->save();

        $tag = new Tag(['name' => 'test-tag']);
        $tag->slug = 'test-tag';
        $tag->save();
        
        $this->story = Story::factory()->create([
            'headline' => 'Test Headline',
            'brief' => 'Test brief.',
            'body_html' => '<p>Test body HTML</p>',
            'category_id' => $category->id,
            'language' => 'en',
            'published_at' => now(),
            'status' => 'published',
            'is_breaking' => false,
        ]);
        $this->story->tags()->attach($tag->id);

        $user = \App\Models\User::factory()->create();
        
        $media = new MediaAsset();
        $media->forceFill([
            'kind' => 'image',
            'caption' => 'Test Caption',
            'title' => 'Test Title',
            'credit_line' => 'UNB',
            'mime' => 'image/jpeg',
            'size_bytes' => 1024,
            'storage_disk' => 's3',
            'original_path' => 'test.jpg',
            'checksum' => 'test-checksum',
            'status' => 'ready',
            'uploaded_by' => $user->id
        ]);
        $media->public_id = 'test-media-id';
        $media->save();
        $this->story->media()->attach($media->id);
        
        // Eager load relations
        $this->story->load(['category', 'tags', 'media']);
    }

    public function test_json_unb_v1_format(): void
    {
        $factory = new WireFormatFactory();
        $output = $factory->generate($this->story, 'json-unb-v1');

        $this->assertEquals("UNB-{$this->story->public_id}.json", $output->filename);
        $this->assertEquals('application/json', $output->contentType);
        
        $data = json_decode($output->content, true);
        $this->assertEquals($this->story->public_id, $data['public_id']);
        $this->assertEquals('Test Headline', $data['headline']);
        $this->assertEquals('Test Category', $data['category']);
        $this->assertCount(1, $data['tags']);
        $this->assertEquals('test-tag', $data['tags'][0]);
        $this->assertCount(1, $data['media']);
        $this->assertEquals('Test Caption', $data['media'][0]['caption']);
        $this->assertEquals('UNB', $data['media'][0]['credit']);
    }

    public function test_newsml_g2_format(): void
    {
        $factory = new WireFormatFactory();
        $output = $factory->generate($this->story, 'newsml-g2');

        $this->assertEquals("UNB-{$this->story->public_id}.xml", $output->filename);
        $this->assertEquals('application/xml', $output->contentType);
        
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $output->content);
        $this->assertStringContainsString('<newsItem', $output->content);
        $this->assertStringContainsString('Test Headline', $output->content);
        $this->assertStringContainsString('<p>Test body HTML</p>', $output->content);
        $this->assertStringContainsString('<title>Test Caption</title>', $output->content);
        
        // Ensure it's valid XML
        $xml = simplexml_load_string($output->content);
        $this->assertNotFalse($xml);
        $this->assertEquals('newsItem', $xml->getName());
    }

    public function test_nitf_format(): void
    {
        $factory = new WireFormatFactory();
        $output = $factory->generate($this->story, 'nitf');

        $this->assertEquals("UNB-{$this->story->public_id}.nitf.xml", $output->filename);
        $this->assertEquals('application/xml', $output->contentType);
        
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $output->content);
        $this->assertStringContainsString('<nitf>', $output->content);
        $this->assertStringContainsString('<title>Test Headline</title>', $output->content);
        $this->assertStringContainsString('<p>Test brief.</p>', $output->content);
        $this->assertStringContainsString('<p>Test body HTML</p>', $output->content);
        $this->assertStringContainsString('<media-caption>Test Caption</media-caption>', $output->content);
        
        // Ensure it's valid XML
        $xml = simplexml_load_string($output->content);
        $this->assertNotFalse($xml);
        $this->assertEquals('nitf', $xml->getName());
    }
}
