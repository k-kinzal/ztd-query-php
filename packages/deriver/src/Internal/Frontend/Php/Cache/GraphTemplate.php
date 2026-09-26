<?php

declare(strict_types=1);

namespace Deriver\Internal\Frontend\Php\Cache;

use Deriver\Internal\Frontend\Php\CallableSource;
use Deriver\Internal\IR\CallableIR;

/**
 * A source-only graph and lexical declarations discovered while lowering it.
 * @visibility root
 */
final class GraphTemplate
{
    /**
     * @param CallableIR $body Immutable lowered graph
     * @param array<string, CallableSource> $closures Lexical closure sources required by the graph
     */
    public function __construct(public readonly CallableIR $body, public readonly array $closures)
    {
    }
}
