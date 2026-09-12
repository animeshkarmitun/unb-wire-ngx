<?php

namespace App\Services\Delivery;

use App\Models\Story;
use App\Services\Delivery\Formats\JsonUnbV1Formatter;
use App\Services\Delivery\Formats\NewsmlG2Formatter;
use App\Services\Delivery\Formats\NitfFormatter;
use InvalidArgumentException;

class WireFormatFactory
{
    public function generate(Story $story, string $format): WireOutput
    {
        $normalized = match (strtolower(trim($format))) {
            'json', 'json-unb-v1', 'unb-v1' => 'json-unb-v1',
            'newsml', 'newsml-g2', 'newsmlg2', 'xml' => 'newsml-g2',
            'nitf', 'nitf-xml' => 'nitf',
            default => $format,
        };

        return match ($normalized) {
            'json-unb-v1' => (new JsonUnbV1Formatter)->format($story),
            'newsml-g2' => (new NewsmlG2Formatter)->format($story),
            'nitf' => (new NitfFormatter)->format($story),
            default => throw new InvalidArgumentException("Unsupported wire format: {$format}"),
        };
    }
}
