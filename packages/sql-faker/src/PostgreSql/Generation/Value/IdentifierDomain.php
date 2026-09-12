<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Value;

use Closure;
use Override;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ValueDomain;

/**

 * Scanner start/continuation alphabets with a safe sampling prefix for generated names.

 */
final class IdentifierDomain implements ValueDomain
{
    /**
     * @param list<string> $excluded Case-insensitive whole words excluded by the source action
     * @param non-empty-string $first
     * @param non-empty-string $rest
     * @param non-empty-string $samplePrefix Prefix avoids keyword lookup during construction
     */
    public function __construct(private readonly string $first, private readonly string $rest, private readonly string $samplePrefix = '_sf', private readonly int $maximum = 64, private readonly array $excluded = [])
    {
    }

    /**

     * @param Closure(positive-int): int $choose

     */
    #[Override]
    public function choose(Closure $choose): string
    {
        return (new CharacterDomain(str_split($this->rest), 0, max(0, $this->maximum - strlen($this->samplePrefix)), $this->samplePrefix))->choose($choose);
    }

    /**

     * @return list<int>

     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        if (!isset($value[$offset]) || !str_contains($this->first, $value[$offset])) {
            return [];
        }
        $start = $offset;
        $ends = [++$offset];
        while (isset($value[$offset]) && str_contains($this->rest, $value[$offset])) {
            $ends[] = ++$offset;
        }
        return array_values(array_filter($ends, fn (int $end): bool => !in_array(strtoupper(substr($value, $start, $end - $start)), $this->excluded, true)));
    }
}
