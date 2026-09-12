<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Value;

use Closure;
use Override;
use SqlFaker\Grammar\Generation\Value\ValueDomain;

/**
 * Reuses one chosen tag at both boundaries; the declared body alphabet excludes dollars.
 */
final class DollarQuotedDomain implements ValueDomain
{
    /**
     * Binds a legal tag domain and a body domain that cannot contain the chosen closing delimiter.
     */
    public function __construct(private readonly ValueDomain $tag, private readonly ValueDomain $body)
    {
    }

    /**
     * @param Closure(positive-int): int $choose
     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $tag = '$' . $this->tag->choose($choose) . '$';
        return $tag . $this->body->choose($choose) . $tag;
    }
    /**
     * Uses the opening tag as a bound delimiter; the first identical tag closes the scanner state.
     * @return list<int>
     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        if (($value[$offset] ?? null) !== '$') {
            return [];
        }
        $tagEnd = strpos($value, '$', $offset + 1);
        if ($tagEnd === false || !in_array($tagEnd, $this->tag->match($value, $offset + 1), true)) {
            return [];
        }
        $delimiter = substr($value, $offset, $tagEnd - $offset + 1);
        $end = strpos($value, $delimiter, $tagEnd + 1);
        if ($end === false || str_contains(substr($value, $tagEnd + 1, $end - $tagEnd - 1), "\0")) {
            return [];
        }
        return [$end + strlen($delimiter)];
    }

}
