<?php

declare(strict_types=1);

namespace AdrienBrault\Instructrice\Eval;

class GenericComparator
{
    /**
     * @see https://chat.openai.com/share/f86936d6-cec3-4dd3-949f-039a5cd0844d
     *
     * @return float Between 0 and 1
     */
    public function scoreSimilarity(mixed $input1, mixed $input2): float
    {
        if (($input1 === null && $input2 !== null)
            || ($input1 !== null && $input2 === null)
        ) {
            return 0;
        }

        // Normalize arrays by sorting by keys
        $this->recursiveKsort($input1);
        $this->recursiveKsort($input2);

        $flat1 = $this->arrayFlatten($input1);
        $flat2 = $this->arrayFlatten($input2);

        // Calculate similarity score
        $allKeys = array_unique(array_merge(array_keys($flat1), array_keys($flat2)));
        $totalSimilarity = 0;
        $totalPossible = \count($allKeys);

        foreach ($allKeys as $key) {
            if (\array_key_exists($key, $flat1) && \array_key_exists($key, $flat2)) {
                if ($flat1[$key] === $flat2[$key]) {
                    ++$totalSimilarity; // Full point for exact match
                } elseif (\is_string($flat1[$key]) && \is_string($flat2[$key])) {
                    $similarityPercent = 0;

                    // todo maybe consider embedding distance/cosine thing?

                    similar_text($flat1[$key], $flat2[$key], $similarityPercent);
                    $totalSimilarity += $similarityPercent / 100; // Add fractional similarity for strings
                }
            }
        }

        // Calculate score
        $score = $totalPossible > 0 ? $totalSimilarity / $totalPossible : 0;

        return round($score, 3);
    }

    private function recursiveKsort(mixed &$array): void
    {
        if (! \is_array($array)) {
            return;
        }
        foreach ($array as &$value) {
            if (\is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
        ksort($array);
    }

    /**
     * @param mixed[] $array
     *
     * @return mixed[]
     */
    private function arrayFlatten(mixed $array): array
    {
        if (! \is_array($array)) {
            return [$array];
        }
        $result = [];
        foreach ($array as $key => $value) {
            if (\is_array($value)) {
                $subArray = $this->arrayFlatten($value);
                foreach ($subArray as $subKey => $subValue) {
                    $result[$key . '.' . $subKey] = $subValue;
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
