<?php

namespace Tests\Unit\Services;

use App\Services\DocxImportService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class DocxImportServiceTest extends TestCase
{
    private DocxImportService $svc;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new DocxImportService;
        $this->tmp = tempnam(sys_get_temp_dir(), 'docx');
    }

    protected function tearDown(): void
    {
        @unlink($this->tmp);
        parent::tearDown();
    }

    private function makeDocx(string $documentXml): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($this->tmp, ZipArchive::OVERWRITE));
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        return $this->tmp;
    }

    private function documentXml(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body.'</w:body></w:document>';
    }

    private function para(string $text, bool $bold = false, ?string $style = null): string
    {
        $pPr = $style ? '<w:pPr><w:pStyle w:val="'.$style.'"/></w:pPr>' : '';
        $rPr = $bold ? '<w:rPr><w:b/></w:rPr>' : '';

        return '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
    }

    public function test_extracts_headline_subhead_byline_and_strips_dateline(): void
    {
        $this->makeDocx($this->documentXml(
            $this->para('Meta says fee hike illegal', style: 'Heading1')
            .$this->para('Opposition vows to move court', bold: true)
            .$this->para('DHAKA, Oct 18: The metropolitan university teachers association called the fee hike illegal.')
            .$this->para('By Staff Correspondent')
        ));

        $res = $this->svc->extract($this->tmp);

        $this->assertTrue($res['heading_found']);
        $this->assertTrue($res['subhead_found']);
        $this->assertTrue($res['byline_found']);
        $this->assertSame('DHAKA', $res['dateline_city']);
        $this->assertSame('Oct 18', $res['dateline_date']);
        $this->assertSame('Staff Correspondent', $res['author']);
        $this->assertSame('Meta says fee hike illegal', $res['blocks'][0]['text']);
        $this->assertSame('headline', $res['assignment'][0]);
        $this->assertSame('Opposition vows to move court', $res['blocks'][1]['text']);
        $this->assertSame('sub_head', $res['assignment'][1]);
        $this->assertSame('body', $res['assignment'][2]);
        $this->assertStringStartsWith('The metropolitan university', $res['blocks'][2]['text']);
        $this->assertStringNotContainsString('DHAKA', $res['blocks'][2]['text']);
        $this->assertSame('byline', $res['assignment'][3]);
    }

    public function test_extracts_bangla_dateline_city(): void
    {
        $this->makeDocx($this->documentXml(
            $this->para('ঢাকায় নতুন মেট্রো ঘোষণা', style: 'Heading1')
            .$this->para('ঢাকা, অক্টোবর ১৮: সরকার আজ নতুন মেট্রো রুটের অনুমোদন দিয়েছে।')
        ));

        $res = $this->svc->extract($this->tmp);

        $this->assertSame('ঢাকা', $res['dateline_city']);
        $this->assertStringStartsWith('সরকার আজ', $res['blocks'][1]['text']);
    }

    public function test_falls_back_to_first_paragraph_when_no_heading_style(): void
    {
        $this->makeDocx($this->documentXml(
            $this->para('A plain document without any styled headings')
            .$this->para('Just two simple paragraphs of body text.')
        ));

        $res = $this->svc->extract($this->tmp);

        $this->assertFalse($res['heading_found']);
        $this->assertSame('headline', $res['assignment'][0]);
        $this->assertSame('body', $res['assignment'][1]);
    }

    public function test_ignores_word_tab_elements_and_empty_paragraphs(): void
    {
        $xml = $this->documentXml(
            $this->para('Headline here', style: 'Heading1')
            .'<w:p/>'
            .'<w:p><w:r><w:t>Alpha</w:t><w:tab/><w:t>Beta</w:t></w:r></w:p>'
        );
        $this->makeDocx($xml);

        $res = $this->svc->extract($this->tmp);

        $this->assertCount(2, $res['blocks']);
        $this->assertSame('Alpha Beta', $res['blocks'][1]['text']);
    }

    public function test_sample_template_round_trips_through_extractor(): void
    {
        file_put_contents($this->tmp, $this->svc->generateSample());

        $res = $this->svc->extract($this->tmp);

        $this->assertTrue($res['heading_found']);
        $this->assertTrue($res['subhead_found']);
        $this->assertTrue($res['byline_found']);
        $this->assertTrue($res['headline_placeholder']);
        $this->assertSame('DHAKA', $res['dateline_city']);
        $this->assertGreaterThanOrEqual(5, count($res['blocks']));
        $this->assertSame('headline', $res['assignment'][0]);
        $this->assertSame('sub_head', $res['assignment'][1]);
        $this->assertSame('byline', $res['assignment'][2]);
    }

    public function test_throws_not_docx_for_corrupt_file(): void
    {
        file_put_contents($this->tmp, 'this is not a zip archive');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not_docx');

        $this->svc->extract($this->tmp);
    }

    public function test_throws_empty_for_document_without_text(): void
    {
        $this->makeDocx($this->documentXml('<w:p/>'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('empty');

        $this->svc->extract($this->tmp);
    }

    public function test_throws_too_long_for_oversized_document(): void
    {
        $body = str_repeat($this->para('A short repeated paragraph to blow the block count.'), 301);
        $this->makeDocx($this->documentXml($body));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('too_long');

        $this->svc->extract($this->tmp);
    }
}
