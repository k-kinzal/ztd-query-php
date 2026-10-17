<?php

declare(strict_types=1);

namespace Deriver\Source\Lowering;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Source\Declaration\CallableSource;

/**
 * A source-only graph and lexical declarations discovered while lowering it.
 * @visibility Deriver\Source
 */
final class LoweredCallable
{
    /**
     * @param CallableGraph $body Immutable lowered graph
     * @param array<string, CallableSource> $closures Lexical closure sources required by the graph
     */
    public function __construct(public readonly CallableGraph $body, public readonly array $closures)
    {
    }
}
