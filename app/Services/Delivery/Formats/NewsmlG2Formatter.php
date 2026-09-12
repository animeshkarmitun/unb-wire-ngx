<?php

namespace App\Services\Delivery\Formats;

use App\Models\Story;
use App\Services\Delivery\WireOutput;

class NewsmlG2Formatter
{
    public function format(Story $story): WireOutput
    {
        $publishedAt = $story->published_at ? $story->published_at->format('Y-m-d\TH:i:s\Z') : now()->format('Y-m-d\TH:i:s\Z');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<newsItem xmlns="http://iptc.org/std/nar/2006-10-01/" standard="NewsML-G2" standardversion="2.32" guid="urn:newsml:unb.news:' . $story->public_id . '">' . "\n";
        
        $xml .= '  <itemMeta>' . "\n";
        $xml .= '    <itemClass qcode="ninat:text"/>' . "\n";
        $xml .= '    <provider uri="http://unb.news"/>' . "\n";
        $xml .= '    <versionCreated>' . $publishedAt . '</versionCreated>' . "\n";
        $xml .= '  </itemMeta>' . "\n";

        $xml .= '  <contentMeta>' . "\n";
        $xml .= '    <language tag="' . htmlspecialchars($story->language ?? 'en') . '"/>' . "\n";
        $xml .= '    <headline>' . htmlspecialchars($story->headline ?? '') . '</headline>' . "\n";
        if ($story->media) {
            foreach ($story->media as $media) {
                $xml .= '    <link rel="irel:associatedMedia" residref="urn:newsml:unb.news.media:' . htmlspecialchars($media->public_id ?? '') . '">' . "\n";
                $xml .= '      <title>' . htmlspecialchars($media->caption ?: ($media->title ?? '')) . '</title>' . "\n";
                $xml .= '    </link>' . "\n";
            }
        }
        $xml .= '  </contentMeta>' . "\n";

        $xml .= '  <contentSet>' . "\n";
        $xml .= '    <inlineXML contenttype="application/xhtml+xml">' . "\n";
        $xml .= '      <html><body>' . "\n";
        
        $body = $story->body_html ?: '<p>' . htmlspecialchars($story->body_text ?? '') . '</p>';
        $xml .= '        ' . $body . "\n";
        
        $xml .= '      </body></html>' . "\n";
        $xml .= '    </inlineXML>' . "\n";
        $xml .= '  </contentSet>' . "\n";
        
        $xml .= '</newsItem>' . "\n";

        return new WireOutput(
            content: $xml,
            filename: "UNB-{$story->public_id}.xml",
            contentType: 'application/xml'
        );
    }
}
