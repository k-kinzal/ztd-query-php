<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableDefinition;

/**
 * Exposes query outputs as the ordered declaration of a derived relation.
 *
 * @visibility SqlSemantics
 */
final class QueryRelation
{
    /**
     * @param list<string> $aliases Optional column alias list
     */
    public static function declaration(BoundStatement $query, string $name, array $aliases, Node $source): TableDefinition
    {
        $columns = [];
        foreach ($query->outputs as $index => $output) {
            $columns[] = new ColumnDefinition($aliases[$index] ?? $output->name ?? '?column?', $output->expression->type, $output->expression->nullability, $source);
        }
        return new TableDefinition('', $name, $columns, [], $source);
    }
}
