<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An action on a column without an operand: SET/DROP NOT NULL, DROP DEFAULT, DROP EXPRESSION, DROP IDENTITY.
 *
 * Mirrors `AT_SetNotNull`, `AT_DropNotNull`, `AT_ColumnDefault` without a default, `AT_DropExpression` and
 * `AT_DropIdentity` (with `missing_ok` for IF EXISTS). The optional COLUMN word is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading a column action
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ALTER COLUMN a DROP NOT NULL');
 *     $statement->statement->commands[0]->kind // => \SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind::DropNotNull
 */
final class ColumnAction implements AlterCommand
{
    use Snapshot;

    /**
     * @param ColumnActionKind $kind The action
     * @param Name $column The column
     */
    public function __construct(public readonly ColumnActionKind $kind, public readonly Name $column)
    {
    }

    /**
     * Checks that the column exists, unless IF EXISTS is written.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        if (!$this->kind->conditional()) {
            (new Alterations())->column($derivation, $environment, $this->column);
        }
    }

    /**
     * Writes ALTER, the column and the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword(...explode(' ', $this->kind->value));
    }
}
