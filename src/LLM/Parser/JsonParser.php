<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM\Parser;

use GregHunt\PartialJson\JsonParser as GregHuntJsonParser;

use function Psl\Regex\replace;

class JsonParser implements ParserInterface
{
    public function __construct(
        private readonly GregHuntJsonParser $jsonParser = new GregHuntJsonParser()
    ) {
    }

    public function parse(?string $content): mixed
    {
        $data = null;
        if ($content === null) {
            return null;
        }

        $content = trim($content);

        if (! str_starts_with($content, '{')
            && ! str_starts_with($content, '[')
            && str_contains($content, '```json')
        ) {
            $content = substr($content, strpos($content, '```json') + \strlen('```json'));
            $content = replace($content, '#(.+)```.+$#m', '\1');
            $content = trim((string) $content);
        }

        if (str_starts_with($content, '{') || str_starts_with($content, '[')) {
            $data = $this->jsonParser->parse($content);
        }

        if (! \is_array($data) && ! \is_string($data)) {
            return null;
        }

        return $data;
    }
}
