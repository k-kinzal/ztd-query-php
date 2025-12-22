<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Ast;

/**
 * Represents a grammar rule definition.
 *
 * Example:
 *   select_stmt:
 *       SELECT select_item_list
 *     | SELECT DISTINCT select_item_list
 *     ;
 *
 * @phpstan-type AlternativeNodeList list<BisonAlternativeNode>
 */
final class BisonRuleNode
{
    /**
     * @param list<BisonAlternativeNode> $alternatives
     */
    public function __construct(
        public readonly string $name,
        public readonly array $alternatives,
    ) {
    }
}
