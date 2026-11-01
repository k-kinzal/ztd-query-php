<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use Closure;
use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;

/**
 * A parser that can report completed grammar reductions while reading input.
 *
 * Observers see visible productions in reduction order, including productions
 * completed before a later syntax error. Hidden grammar actions produce no
 * node. The observer does not change parsing or the returned lossless tree.
 *
 * @visibility public
 * @example Declare a consumer of the reduction contract
 *     $observe = static fn (\SqlParser\Parser\ReductionParser $parser, string $sql, \Closure $listener): \SqlParser\Parser\Node => $parser->parse($sql, $listener);
 *     $observe instanceof \Closure // => true
 */
interface ReductionParser extends SqlParser
{
    /**
     * Parses normally and optionally observes completed nodes and their lookahead.
     *
     * @param (Closure(Node, Token): void)|null $reduced Receives each visible reduction
     * @throws SourceException When the text is not accepted by the grammar
     */
    public function parse(string $sql, ?Closure $reduced = null): Node;
}
