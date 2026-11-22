<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Key;

use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Schema\LiteralColumn;

/**
 * Resolves the slot and comparison rule of one table key term.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class KeyTermLowering
{
    /**
     * Resolves a column term within its owning declaration.
     * @param list<TableColumn> $columns The declaration's ordered columns
     * @throws InvalidConstruction When the term does not denote a column in the admitted key domain
     */
    public function lower(SortTerm $term, array $columns): KeyTerm
    {
        Check::input($term->nulls === null, 'A key term does not have an explicit NULL order.');
        $expression = $term->expression;
        $collation = null;
        while ($expression instanceof Grouped || $expression instanceof Collate) {
            if ($expression instanceof Collate) {
                $explicit = Collation::tryFrom(strtr($expression->collation->value, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'));
                Check::input($explicit !== null, 'A key term needs a builtin collation.');
                $collation ??= $explicit;
            }
            $expression = $expression->operand;
        }
        $name = match (true) {
            $expression instanceof ColumnUse && $expression->qualifier === null => $expression->name,
            $expression instanceof DoubleQuotedWord => $expression->word,
            $expression instanceof LiteralColumn => $expression->name(),
            default => null,
        };
        Check::input($name !== null, 'A key term needs one unqualified declared column.');
        foreach ($columns as $slot => $column) {
            if (Comparison::AsciiInsensitive->equal($column->name->value, $name->value)) {
                return new KeyTerm($slot, $term->direction === SortDirection::Descending, $collation ?? $column->collation);
            }
        }

        throw new InvalidConstruction('A key term has no owning column.');
    }
}
