<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * ALTER [ COLUMN ] ... OPTIONS ( ... ): changes the options of a column of a foreign table.
 *
 * Mirrors `AT_AlterColumnGenericOptions`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Changing column options
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER FOREIGN TABLE f ALTER a OPTIONS (ADD x 'y')");
 *     $statement->toString() // => "ALTER FOREIGN TABLE f ALTER a OPTIONS (ADD x 'y')"
 */
final class ColumnForeignOptions implements AlterCommand
{
    use Snapshot;

    /**
     * @var non-empty-list<AlteredOption> The changes
     */
    public readonly array $options;

    /**
     * @param Name $column The column
     * @param list<AlteredOption> $options The changes
     */
    public function __construct(public readonly Name $column, array $options)
    {
        $this->options = Check::listOf($options, AlteredOption::class, 'Option changes are altered options.', 1);
    }

    /**
     * Checks that the column exists.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
    }
}
