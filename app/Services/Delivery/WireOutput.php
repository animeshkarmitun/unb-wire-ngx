<?php

namespace App\Services\Delivery;

class WireOutput
{
    public function __construct(
        public readonly string $content,
        public readonly string $filename,
        public readonly string $contentType
    ) {}
}
