<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * ALTER [ COLUMN ] ... OPTIONS ( ... ): changes the options of a column of a foreign table.
 *
 * Mirrors `AT_AlterColumnGenericOptions`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
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
