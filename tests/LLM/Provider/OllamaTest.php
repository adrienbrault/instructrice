<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Tests\LLM\Provider;

use AdrienBrault\Instructrice\LLM\Provider\Ollama;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ollama::class)]
class OllamaTest extends TestCase
{
    public function testCreate128k(): void
    {
        $config = Ollama::create('phi3:14b-medium-128k-instruct-q5_K_M');

        self::assertSame(128000, $config->contextWindow);
        self::assertSame('phi3:14b-medium-128k-instruct-q5_K_M', $config->model);
    }

    public function testCreate4k(): void
    {
        $config = Ollama::create('phi3:14b-medium-4k-instruct-q5_K_M');

        self::assertSame(4000, $config->contextWindow);
        self::assertSame('phi3:14b-medium-4k-instruct-q5_K_M', $config->model);
    }

    public function testCreateWithCustomContextLength(): void
    {
        $config = Ollama::create('phi3:14b-medium-4k-instruct-q5_K_M', 5000);

        self::assertSame(5000, $config->contextWindow);
        self::assertSame('phi3:14b-medium-4k-instruct-q5_K_M', $config->model);
    }

    public function testCreateWithLabel(): void
    {
        $config = Ollama::create('model', label: 'test');

        self::assertSame('test', $config->label);
    }
}
