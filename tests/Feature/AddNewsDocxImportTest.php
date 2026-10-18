<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddNews;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class AddNewsDocxImportTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(CategorySeeder::class);
        $this->seed(SettingSeeder::class);

        $this->editor = User::factory()->create([
            'role_id' => Role::where('name', 'Editor')->first()->id,
        ]);
        $this->business = User::factory()->create([
            'role_id' => Role::where('name', 'Business Team')->first()->id,
        ]);
    }

    private function docxFile(string $name = 'story.docx'): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Wizard import headline</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>DHAKA, Oct 18: Body paragraph from the uploaded document.</w:t></w:r></w:p>'
            .'</w:body></w:document>');
        $zip->close();

        return UploadedFile::fake()->createWithContent($name, (string) file_get_contents($tmp));
    }

    public function test_upload_rejects_non_docx_with_friendly_error(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->set('docUpload', UploadedFile::fake()->create('notes.txt', 10))
            ->assertSet('docError', fn ($v) => str_contains($v, 'Only Word'));
    }

    public function test_upload_rejects_fake_docx_archive(): void
    {
        $this->actingAs($this->editor);

        Livewire::test(AddNews::class)
            ->set('docUpload', UploadedFile::fake()->createWithContent('fake.docx', 'definitely not a zip'))
            ->assertSet('docError', fn ($v) => str_contains($v, 'Not a valid Word'));
    }

    public function test_upload_parses_real_docx_into_mapping_state(): void
    {
        $this->actingAs($this->editor);

        $cmp = Livewire::test(AddNews::class)
            ->set('docUpload', $this->docxFile())
            ->assertSet('docError', '');

        $this->assertSame('Wizard import headline', $cmp->get('docImport')['blocks'][0]['text']);
        $this->assertSame('headline', $cmp->get('docImport')['assignment'][0]);
        $this->assertSame('DHAKA', $cmp->get('docImport')['dateline_city']);
        $this->assertSame('story.docx', $cmp->get('docImport')['file_name']);
    }

    public function test_sample_template_downloads_for_permitted_roles(): void
    {
        $this->actingAs($this->editor);

        $resp = $this->get(route('admin.news.import-sample'));

        $resp->assertOk();
        $resp->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $this->assertStringStartsWith('PK', $resp->getContent());
    }

    public function test_sample_template_is_denied_without_stories_create(): void
    {
        $this->actingAs($this->business);

        $this->get(route('admin.news.import-sample'))->assertForbidden();
    }

    public function test_apply_is_denied_without_stories_edit_permission(): void
    {
        $this->actingAs($this->business);

        Livewire::test(AddNews::class)
            ->set('docImport', [
                'blocks' => [['kind' => 'heading', 'text' => 'X']],
                'assignment' => ['headline'],
                'author' => null,
                'dateline_city' => null,
                'dateline_date' => null,
                'heading_found' => true,
                'subhead_found' => false,
                'byline_found' => false,
                'words' => 1,
                'file_name' => 'x.docx',
            ])
            ->set('docAssignment', ['headline'])
            ->call('applyDocImport')
            ->assertForbidden();
    }
}
