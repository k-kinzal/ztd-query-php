<?php

declare(strict_types=1);

namespace Deriver\Source\Cache;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Source\Declaration\CallableSource;

/**
 * A source-only graph and lexical declarations discovered while lowering it.
 * @visibility root
 */
final class GraphTemplate
{
    /**
     * @param CallableGraph $body Immutable lowered graph
     * @param array<string, CallableSource> $closures Lexical closure sources required by the graph
     */
    public function __construct(public readonly CallableGraph $body, public readonly array $closures)
    {
    }
}
