<?php

namespace App\Services;

/**
 * Extracts text blocks from a .docx (Open XML) file and suggests a wizard
 * field mapping — headline, sub-head, byline, dateline city and body.
 *
 * A .docx is a ZIP archive; the main document lives at word/document.xml.
 * Parsing stays dependency-free (ZipArchive + SimpleXML) because only text
 * and paragraph styling are needed — images are a later phase.
 */
class DocxImportService
{
    /** Wire-payload guard: anything longer is rejected. */
    public const MAX_PARAGRAPHS = 300;

    private const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const HEADING_STYLES = ['title', 'heading1', 'heading2'];

    /**
     * "DHAKA, Oct 18:" / "ঢাকা, অক্টোবর ১৮:" style dateline prefix.
     * City: uppercase Latin or Bangla letters; the date part is permissive.
     */
    private const DATELINE_RE = '/^\s*([\p{Lu}\x{0980}-\x{09FF}][\p{Lu}\x{0980}-\x{09FF} .\-]{0,39})\s*,\s*([^:：]{0,40})[:：]\s*/u';

    private const BYLINE_RE = '/^\s*(?:by|BY|By)\s*[:\-]?\s+(.{2,80})$/u';

    /**
     * @return array{blocks: array<int, array{kind: string, text: string}>, assignment: array<int, string>, author: ?string, dateline_city: ?string, dateline_date: ?string, heading_found: bool, subhead_found: bool, byline_found: bool, words: int}
     *
     * @throws \RuntimeException codes: not_docx, empty, too_long
     */
    public function extract(string $path): array
    {
        $paras = $this->parseParagraphs($this->documentXml($path));

        if ($paras === []) {
            throw new \RuntimeException('empty');
        }
        if (count($paras) > self::MAX_PARAGRAPHS) {
            throw new \RuntimeException('too_long');
        }

        // Headline: first Word heading/title style, otherwise first paragraph (a guess).
        $headlineIdx = null;
        foreach ($paras as $i => $p) {
            if ($p['heading']) {
                $headlineIdx = $i;
                break;
            }
        }
        $headingFound = $headlineIdx !== null;
        $headlineIdx ??= 0;

        // Byline: "By <name>" line after the headline.
        $bylineIdx = null;
        $author = null;
        foreach ($paras as $i => $p) {
            if ($i === $headlineIdx) {
                continue;
            }
            if (preg_match(self::BYLINE_RE, $p['text'], $m) === 1) {
                $bylineIdx = $i;
                $author = trim($m[1]);
                break;
            }
        }

        // Sub-head: next short bold/heading line that is not the byline.
        $subheadIdx = null;
        for ($i = $headlineIdx + 1; $i < count($paras); $i++) {
            if ($i === $bylineIdx) {
                continue;
            }
            $p = $paras[$i];
            if (($p['bold'] || $p['heading']) && $this->wordCount($p['text']) <= 15) {
                $subheadIdx = $i;
                break;
            }
        }

        // Dateline: strip "CITY, date:" prefix from the first body candidate.
        $datelineCity = null;
        $datelineDate = null;
        foreach ($paras as $i => $p) {
            if (in_array($i, [$headlineIdx, $subheadIdx, $bylineIdx], true)) {
                continue;
            }
            if (preg_match(self::DATELINE_RE, $p['text'], $m) === 1) {
                $datelineCity = trim($m[1]);
                $datelineDate = trim($m[2]);
                $paras[$i]['text'] = mb_substr($p['text'], mb_strlen($m[0]));
                break;
            }
        }

        $blocks = [];
        $assignment = [];
        foreach ($paras as $i => $p) {
            $kind = $p['heading'] ? 'heading' : ($p['bold'] ? 'bold' : 'para');
            $blocks[] = ['kind' => $kind, 'text' => trim($p['text'])];
            $assignment[] = match ($i) {
                $headlineIdx => 'headline',
                $subheadIdx => 'sub_head',
                $bylineIdx => 'byline',
                default => 'body',
            };
        }

        return [
            'blocks' => $blocks,
            'assignment' => $assignment,
            'author' => $author,
            'dateline_city' => $datelineCity,
            'dateline_date' => $datelineDate,
            'heading_found' => $headingFound,
            'subhead_found' => $subheadIdx !== null,
            'byline_found' => $bylineIdx !== null,
            'headline_placeholder' => str_contains($blocks[$headlineIdx]['text'], '['),
            'words' => array_sum(array_map(fn ($b) => $this->wordCount($b['text']), $blocks)),
        ];
    }

    /**
     * Build the download-ready sample template .docx that mirrors the exact
     * structure extract() expects. Generated on the fly so template and
     * parser can never drift apart.
     */
    public function generateSample(): string
    {
        $zip = new \ZipArchive;
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('template_tmp_failed');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/styles.xml', $this->sampleStylesXml());
        $zip->addFromString('word/document.xml', $this->sampleDocumentXml());
        $zip->close();

        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    private function sampleStylesXml(): string
    {
        $w = self::W_NS;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            ."<w:styles xmlns:w=\"$w\">"
            .'<w:style w:type="paragraph" w:styleId="Heading1">'
            .'<w:name w:val="heading 1"/>'
            .'<w:pPr><w:spacing w:after="240"/></w:pPr>'
            .'<w:rPr><w:rFonts w:ascii="Georgia" w:hAnsi="Georgia"/><w:b/><w:sz w:val="36"/><w:szCs w:val="36"/></w:rPr>'
            .'</w:style>'
            .'</w:styles>';
    }

    private function sampleDocumentXml(): string
    {
        $w = self::W_NS;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            ."<w:document xmlns:w=\"$w\"><w:body>"
            .'<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>[HEADLINE — write the main headline here, under 100 characters]</w:t></w:r></w:p>'
            .'<w:p><w:r><w:rPr><w:b/></w:rPr><w:t>[SUB-HEAD — one short bold line summarizing the story]</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>By [Reporter Name]</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>DHAKA, Oct 18: [First paragraph — always start the body with CITY, Mon D: then the news]</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>[Body paragraph two — keep paragraphs short and factual]</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>[Body paragraph three — end with background or context]</w:t></w:r></w:p>'
            .'</w:body></w:document>';
    }

    private function documentXml(string $path): string
    {
        $zip = new \ZipArchive;
        $code = $zip->open($path);
        if ($code !== true) {
            throw new \RuntimeException('not_docx');
        }
        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }
        if ($xml === false) {
            throw new \RuntimeException('not_docx');
        }

        return $xml;
    }

    /**
     * Flatten word/document.xml into non-empty paragraphs with heading/bold flags.
     *
     * @return array<int, array{heading: bool, bold: bool, text: string}>
     */
    private function parseParagraphs(string $xml): array
    {
        libxml_use_internal_errors(true);
        $root = simplexml_load_string($xml);
        libxml_clear_errors();
        if ($root === false) {
            throw new \RuntimeException('not_docx');
        }

        $paras = [];
        foreach ($root->xpath('//w:p') as $p) {
            $text = '';
            $boldLen = 0;
            $totalLen = 0;

            foreach ($p->xpath('.//w:r') as $run) {
                $runText = '';
                foreach ($run->children(self::W_NS) as $child) {
                    $name = $child->getName();
                    if ($name === 't') {
                        $runText .= (string) $child;
                    } elseif ($name === 'tab' || $name === 'br') {
                        $runText .= ' ';
                    }
                }
                if ($runText === '') {
                    continue;
                }
                $text .= $runText;
                $totalLen += mb_strlen($runText);
                if ($this->runIsBold($run)) {
                    $boldLen += mb_strlen($runText);
                }
            }

            $text = trim($text);
            if ($text === '') {
                continue;
            }

            $style = '';
            $styleNodes = $p->xpath('./w:pPr/w:pStyle/@w:val');
            if ($styleNodes !== false && count($styleNodes) > 0) {
                $style = strtolower((string) $styleNodes[0]);
            }

            $paras[] = [
                'heading' => in_array($style, self::HEADING_STYLES, true),
                'bold' => $totalLen > 0 && ($boldLen / $totalLen) >= 0.5,
                'text' => $text,
            ];
        }

        return $paras;
    }

    private function runIsBold(\SimpleXMLElement $run): bool
    {
        $b = $run->xpath('./w:rPr/w:b');
        if ($b === false || count($b) === 0) {
            return false;
        }
        $val = (string) $b[0]['val'];

        return ! in_array(strtolower($val), ['0', 'false', 'none'], true);
    }

    private function wordCount(string $text): int
    {
        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? 0 : count($parts);
    }
}
