<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnCompression;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ColumnStorage;

/**
 * ALTER [ COLUMN ] ... SET STORAGE or SET COMPRESSION.
 *
 * Mirrors `AT_SetStorage` and `AT_SetCompression`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class ColumnStorageChange implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param ColumnStorage|ColumnCompression $setting The new storage or compression
     */
    public function __construct(public readonly Name $column, public readonly ColumnStorage|ColumnCompression $setting)
    {
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
        $out->keyword('ALTER')->name($this->column)->keyword('SET')->node($this->setting);
    }
}
