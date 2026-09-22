<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Model\ResultStatement;
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
    public static function declaration(ResultStatement $query, string $name, array $aliases, Node $source): TableDefinition
    {
        return self::columns($query->resultColumns(), $name, $aliases, $source);
    }

    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<string> $aliases
     */
    public static function columns(array $outputs, string $name, array $aliases, Node $source): TableDefinition
    {
        $columns = [];
        $resolved = true;
        foreach ($outputs as $index => $output) {
            if ($output->expression->kind === \SqlSemantics\Model\ExpressionKind::Wildcard) {
                $resolved = false;
                continue;
            }
            $columns[] = new ColumnDefinition($aliases[$index] ?? $output->name ?? '?column?', $output->expression->type, $output->expression->nullability, $source);
        }
        return new TableDefinition('', $name, $columns, [], $source, $resolved);
    }
}
