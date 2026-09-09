<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

use Closure;
use Override;

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
}
