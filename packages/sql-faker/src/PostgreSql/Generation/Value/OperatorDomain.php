<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Value;

use Closure;
use InvalidArgumentException;
use Override;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ValueDomain;

/**

 * Scanner operator runs with explicit fixed-token and comment boundaries.

 */
final class OperatorDomain implements ValueDomain
{
    /**
     * @param non-empty-string $characters
     * @param non-empty-string $nonSql Characters permitting a trailing plus or minus
     * @param list<string> $fixed
     * @param list<string> $comments
     */
    public function __construct(private readonly string $characters, private readonly string $nonSql, private readonly array $fixed, private readonly array $comments)
    {
    }

    /**

     * @param Closure(positive-int): int $choose
     * @throws InvalidArgumentException When the declared alphabet cannot produce a safe operator

     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $safe = array_values(array_filter(str_split($this->characters), static fn (string $atom): bool => $atom !== '/' && $atom !== '-'));
        if ($safe === []) {
            throw new InvalidArgumentException('Require an operator alphabet containing a safe character.');
        }
        return (new CharacterDomain($safe, 0, 31, '?'))->choose($choose);
    }

    /**

     * @return list<int>

     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $start = $offset;
        $ends = [];
        while (isset($value[$offset]) && $offset - $start < 63 && str_contains($this->characters, $value[$offset])) {
            ++$offset;
            $operator = substr($value, $start, $offset - $start);
            foreach ($this->comments as $comment) {
                if (str_contains($operator, $comment)) {
                    return $ends;
                }
            }
            $last = $operator[strlen($operator) - 1];
            if (!in_array($operator, $this->fixed, true)
                && (strlen($operator) === 1 || ($last !== '+' && $last !== '-') || strpbrk($operator, $this->nonSql) !== false)) {
                $ends[] = $offset;
            }
        }
        return $ends;
    }
}
