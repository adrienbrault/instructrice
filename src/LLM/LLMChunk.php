<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTimeImmutable;

use function Psl\Regex\replace;

class LLMChunk
{
    public readonly string $propertyPath;

    public function __construct(
        public readonly string $content,
        public readonly mixed $data,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly Cost $cost,
        public readonly DateTimeImmutable $requestedAt,
        public readonly DateTimeImmutable $firstTokenReceivedAt,
    ) {
        $this->propertyPath = self::getDataLastPropertyPath($data);
    }

    public function getTokensPerSecond(): float
    {
        $elapsed = abs(Carbon::now()->diff($this->requestedAt)->totalSeconds ?? 1);

        return $this->promptTokens / $elapsed;
    }

    private static function getDataLastPropertyPath(mixed $data = null, string $currentPath = ''): string
    {
        if (\is_object($data)) {
            $data = (array) $data;
        }

        if (\is_array($data)) {
            $lastKey = null;
            foreach ($data as $key => $value) {
                $lastKey = $key;
            }

            if ($lastKey !== null) {
                $newPath = $currentPath . (\is_array($data) && ! \is_string($lastKey) ? '[' . $lastKey . ']' : '.' . $lastKey);

                return self::getDataLastPropertyPath($data[$lastKey], $newPath);
            }
        }

        return $currentPath;
    }

    public function getTimeToFirstToken(): CarbonInterval
    {
        return CarbonInterval::instance($this->firstTokenReceivedAt->diff($this->requestedAt));
    }

    public function getFormattedCost(): string
    {
        return $this->formatCost($this->cost->calculate(
            $this->promptTokens,
            $this->completionTokens
        ));
    }

    public function getFormattedPromptCost(): string
    {
        return $this->formatCost($this->cost->calculate($this->promptTokens, 0));
    }

    public function getFormattedCompletionCost(): string
    {
        return $this->formatCost($this->cost->calculate(0, $this->completionTokens));
    }

    private function formatCost(float $cost): string
    {
        return match ($cost) {
            0.0 => 'Free',
            default => '$' . replace(
                number_format($cost, 10),
                '#(0[.]0*[0-9]{2}).+$#',
                '\1'
            )
        };
    }

    public function getTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
