<?php

namespace App\Services\Delivery\Formats;

use App\Models\Story;
use App\Services\Delivery\WireOutput;

class NitfFormatter
{
    public function format(Story $story): WireOutput
    {
        $publishedAt = $story->published_at ? $story->published_at->format('Ymd\THis\Z') : now()->format('Ymd\THis\Z');
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<nitf>' . "\n";
        
        $xml .= '  <head>' . "\n";
        $xml .= '    <title>' . htmlspecialchars($story->headline ?? '') . '</title>' . "\n";
        $xml .= '    <docdata>' . "\n";
        $xml .= '      <date.issue norm="' . $publishedAt . '"/>' . "\n";
        $xml .= '    </docdata>' . "\n";
        $xml .= '  </head>' . "\n";
        
        $xml .= '  <body>' . "\n";
        $xml .= '    <body.head>' . "\n";
        $xml .= '      <hedline>' . "\n";
        $xml .= '        <hl1>' . htmlspecialchars($story->headline ?? '') . '</hl1>' . "\n";
        $xml .= '      </hedline>' . "\n";
        if ($story->brief) {
            $xml .= '      <abstract>' . "\n";
            $xml .= '        <p>' . htmlspecialchars($story->brief) . '</p>' . "\n";
            $xml .= '      </abstract>' . "\n";
        }
        $xml .= '    </body.head>' . "\n";
        
        $xml .= '    <body.content>' . "\n";
        if ($story->media) {
            foreach ($story->media as $media) {
                $xml .= '      <media media-type="' . htmlspecialchars($media->kind ?? 'image') . '">' . "\n";
                $xml .= '        <media-reference source="urn:unb.news.media:' . htmlspecialchars($media->public_id ?? '') . '"/>' . "\n";
                $xml .= '        <media-caption>' . htmlspecialchars($media->caption ?: ($media->title ?? '')) . '</media-caption>' . "\n";
                $xml .= '      </media>' . "\n";
            }
        }
        
        $body = $story->body_html ?: '<p>' . htmlspecialchars($story->body_text ?? '') . '</p>';
        $xml .= '      <block>' . "\n";
        $xml .= '        ' . $body . "\n";
        $xml .= '      </block>' . "\n";
        
        $xml .= '    </body.content>' . "\n";
        $xml .= '  </body>' . "\n";
        $xml .= '</nitf>' . "\n";

        return new WireOutput(
            content: $xml,
            filename: "UNB-{$story->public_id}.nitf.xml",
            contentType: 'application/xml'
        );
    }
}
