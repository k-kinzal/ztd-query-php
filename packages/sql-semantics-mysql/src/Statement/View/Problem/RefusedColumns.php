<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\View\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnNameRule;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A modern view column list refused before its query is prepared.
 *
 * The view identity retains the default-database dependency. The refused column
 * is a decoded name; no source SQL or parser tree is retained.
 * Native MySQL 8.0.44, 8.4.7 and 9.1.0 reject these names even in unreachable
 * stored instructions. MySQL 5.6.51 and 5.7.44 accept the same column lists.
 *
 * @visibility public
 * @example Describing an empty column name
 *     (new \SqlSemantics\Platform\MySql\Statement\View\Problem\RefusedColumns(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('v')), new \SqlSemantics\Statement\Identifier\Name('')))->message() // => "Incorrect column name ''"
 */
final class RefusedColumns implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $view The view identity before reading the query
     * @param Name $column The first invalid explicit column name
     * @param QualifiedName|null $program The enclosing stored declaration, whose database is resolved first
     */
    public function __construct(public readonly QualifiedName $view, public readonly Name $column, public readonly ?QualifiedName $program = null)
    {
        Check::input(!(new ColumnNameRule())->valid($column->value), 'A refused view column name is outside the modern column-name domain.');
    }

    /**
     * Describes the refused column operand.
     */
    public function message(): string
    {
        return "Incorrect column name '" . $this->column->value . "'";
    }
}
