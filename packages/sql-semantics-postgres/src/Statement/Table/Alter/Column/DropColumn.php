<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * DROP [ COLUMN ]: removes a column.
 *
 * Mirrors `AT_DropColumn` with `missing_ok` and `behavior`. The column must exist unless IF EXISTS is
 * written.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Dropping a column
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t DROP COLUMN IF EXISTS c CASCADE');
 *     $statement->toString() // => 'ALTER TABLE t DROP IF EXISTS c CASCADE'
 */
final class DropColumn implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior CASCADE or RESTRICT, when written
     */
    public function __construct(
        public readonly Name $column,
        public readonly bool $ifExists = false,
        public readonly ?DropBehavior $behavior = null,
    ) {
    }

    /**
     * Checks that the column exists, unless IF EXISTS is written.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        if (!$this->ifExists) {
            (new Alterations())->column($derivation, $environment, $this->column);
        }
    }

    /**
     * Writes DROP, the column and the behavior.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->column);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
