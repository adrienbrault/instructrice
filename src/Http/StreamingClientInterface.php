<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Http;

interface StreamingClientInterface
{
    /**
     * @param array<string, mixed> $headers
     *
     * @return iterable<string> All the data: values
     */
    public function request(
        string $method,
        string $url,
        mixed $jsonBody,
        array $headers = [],
    ): iterable;
}
