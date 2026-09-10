<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use Override;

/**

 * A finite spelling choice, including optional empty components.

 */
final class WordDomain implements ValueDomain
{
    /**
     * @param non-empty-list<string> $words
     */
    public function __construct(private readonly array $words, private readonly bool $caseInsensitive = false)
    {
    }

    /**

     * @param Closure(positive-int): int $choose

     */
    #[Override]
    public function choose(Closure $choose): string
    {
        return $this->words[$choose(count($this->words))];
    }

    /**

     * @return list<int>

     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $ends = [];
        foreach ($this->words as $word) {
            $part = substr($value, $offset, strlen($word));
            if ($part === $word || ($this->caseInsensitive && strcasecmp($part, $word) === 0)) {
                $ends[] = $offset + strlen($word);
            }
        }
        return array_values(array_unique($ends));
    }
}
