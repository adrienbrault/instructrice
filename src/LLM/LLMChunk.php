<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\LLM;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateTimeImmutable;

use function Psl\Regex\replace;

class LLMChunk
{
    public function __construct(
        public mixed $data,
        public int $promptTokens,
        public int $completionTokens,
        public Cost $cost,
        public DateTimeImmutable $requestedAt,
        public DateTimeImmutable $firstTokenReceivedAt,
    ) {
    }

    public function getTokensPerSecond(): float
    {
        $elapsed = abs(Carbon::now()->diff($this->requestedAt)->totalSeconds);

        return $this->getTokens() / $elapsed;
    }

    public function getDataLastPropertyPath(mixed $data = null, string $currentPath = ''): string
    {
        if ($currentPath === '') {
            $data = $this->data;
        }

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

                return $this->getDataLastPropertyPath($data[$lastKey], $newPath);
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
                '#0+$#',
                ''
            )
        };
    }

    public function getTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }
}
