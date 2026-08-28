<?php

namespace App\Services;

class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p','br','strong','b','em','i','u','s','a','ul','ol','li','blockquote','h1','h2','h3','code','pre','span','div'];
    private const ALLOWED_ATTR = ['href','title','alt','src'];

    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') return '';
        $html = trim($html);
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<div>'.$html.'</div>';
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//*') as $node) {
            $tag = strtolower($node->nodeName);
            if ($tag === 'div' && $node->parentNode instanceof \DOMDocument) continue;
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if (in_array($tag, ['script','style','iframe','object','embed','form','input','button','link','meta'])) {
                    $node->parentNode->removeChild($node);
                } else {
                    $frag = $doc->createDocumentFragment();
                    while ($node->firstChild) $frag->appendChild($node->firstChild);
                    $node->parentNode->replaceChild($frag, $node);
                }
                continue;
            }
            if ($node->hasAttributes()) {
                $toRemove = [];
                foreach ($node->attributes as $attr) {
                    $name = strtolower($attr->nodeName);
                    $val = $attr->nodeValue;
                    if (str_starts_with($name, 'on')) { $toRemove[] = $name; continue; }
                    if (!in_array($name, self::ALLOWED_ATTR, true)) { $toRemove[] = $name; continue; }
                    if (in_array($name, ['href','src'], true)) {
                        $v = trim(strtolower($val));
                        if (str_starts_with($v, 'javascript:') || str_starts_with($v, 'data:text/html') || str_starts_with($v, 'vbscript:')) { $toRemove[] = $name; continue; }
                        if ($name === 'src' && !preg_match('#^(https?://|/|data:image/(png|jpeg|gif|webp);base64,)#i', $val)) { $toRemove[] = $name; continue; }
                    }
                }
                foreach ($toRemove as $n) $node->removeAttribute($n);
            }
        }
        $div = $doc->getElementsByTagName('div')->item(0);
        if (!$div) return '';
        $out = '';
        foreach ($div->childNodes as $c) $out .= $doc->saveHTML($c);
        return $out;
    }

    public static function text(?string $html): string
    {
        return trim(strip_tags(self::clean($html)));
    }
}
