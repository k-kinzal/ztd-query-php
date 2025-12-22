<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a single alternative in a grammar rule.
 *
 * Example: SELECT select_item_list { $$ = $2; } %prec LOWER_THAN_EMPTY
 *
 * @phpstan-type SymbolNodeList list<BisonSymbolNode>
 */
final class BisonAlternativeNode
{
    /**
     * @param list<BisonSymbolNode> $symbols
     */
    public function __construct(
        public readonly array $symbols,
        public readonly ?string $action,
        public readonly ?string $prec,
        public readonly ?int $dprec,
        public readonly ?string $merge,
    ) {
    }
}
