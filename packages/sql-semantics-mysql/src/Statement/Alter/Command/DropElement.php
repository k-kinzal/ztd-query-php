<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;

/**
 * `DROP [COLUMN] c [RESTRICT | CASCADE]`, `DROP PRIMARY KEY`, `DROP FOREIGN KEY`, `DROP INDEX`, `DROP CHECK` or `DROP CONSTRAINT`.
 *
 * Mirrors PT_alter_table_drop and its subclasses: one action with the kind
 * of element. The primary key has no name; every other kind names its
 * element (5.x lets a table qualify the name). RESTRICT and CASCADE are
 * accepted only after a column and have no effect; they are kept as written.
 * Only columns are part of a declaration context, so only a dropped column
 * is checked (see AlterTable). The word COLUMN is optional and always
 * written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility SqlSemantics
 */
final class DropElement implements AlterCommand
{
    use Snapshot;

    /**
     * @param ElementKind $kind The kind of element
     * @param ColumnName|null $name The element name; null only for the primary key
     * @param DropBehavior|null $behavior RESTRICT or CASCADE after a column, when written
     */
    public function __construct(public readonly ElementKind $kind, public readonly ?ColumnName $name = null, public readonly ?DropBehavior $behavior = null)
    {
        Check::input(($name === null) === ($kind === ElementKind::PrimaryKey), 'Every dropped element but the primary key is named.');
        Check::input($behavior === null || $kind === ElementKind::Column, 'Only a dropped column takes RESTRICT or CASCADE.');
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', ...explode(' ', $this->kind->value))->node($this->name);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
