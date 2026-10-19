<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation;

use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnFlags;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\MultiplePrimaryKeys;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Statement\Fact\Diagnostic;

/**
 * Checks fixed relationships within a complete CREATE declaration.
 * A creation query can supply missing key columns, but cannot remove repeated
 * declared columns, repeated index parts or multiple primary keys.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DeclarationConstraints
{
    /**
     * Finds a structural contradiction without deriving an Operation or opening a catalog.
     * The returned condition identifies the retained role, not runtime error priority.
     *
     * @param list<TableElement> $elements Complete declarations in written order
     */
    public function problem(array $elements, ?TableQuery $query): ?Diagnostic
    {
        $names = [];
        $primary = 0;
        foreach ($elements as $element) {
            if ($element instanceof ColumnDefinition) {
                $name = $element->name->column;
                $key = mb_strtolower($name->value, 'UTF-8');
                if (isset($names[$key])) {
                    return new DuplicateColumn($name);
                }
                $names[$key] = true;
                $primary += (new ColumnFlags())->flags($element->specification)[2] ? 1 : 0;
            } elseif ($element instanceof IndexDefinition && $element->kind === IndexKind::Primary) {
                $primary++;
            }
        }
        if ($primary > 1) {
            return new MultiplePrimaryKeys();
        }
        foreach ($elements as $element) {
            $parts = $element instanceof IndexDefinition ? $element->parts : ($element instanceof ForeignKey ? $element->columns : []);
            $problem = $this->parts($parts, $names, $query === null, $element instanceof IndexDefinition);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Checks index occurrences and references to a closed declaration namespace.
     *
     * @param list<object> $parts Ordered key parts
     * @param array<string, true> $names Folded declared column names
     */
    public function parts(array $parts, array $names, bool $complete, bool $index): ?Diagnostic
    {
        $seen = [];
        foreach ($parts as $part) {
            if (!$part instanceof ColumnPart) {
                continue;
            }
            $key = mb_strtolower($part->column->value, 'UTF-8');
            if ($complete && !isset($names[$key])) {
                return new UnknownKeyColumn($part->column);
            }
            if ($index && isset($seen[$key])) {
                return new DuplicateColumn($part->column);
            }
            $seen[$key] = true;
        }
        return null;
    }
}
