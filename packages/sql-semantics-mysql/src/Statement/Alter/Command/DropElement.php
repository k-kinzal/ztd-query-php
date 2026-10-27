<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * `DROP [COLUMN] c [RESTRICT | CASCADE]`, `DROP PRIMARY KEY`, `DROP FOREIGN KEY`, `DROP INDEX`, `DROP CHECK` or `DROP CONSTRAINT`.
 *
 * Mirrors PT_alter_table_drop and its subclasses: one action with the kind
 * of element. The primary key has no name; every other kind names its
 * element (5.x lets a table qualify the name). RESTRICT and CASCADE are
 * accepted only after a column and have no effect; they are kept as written.
 * Only columns are part of a declaration context, so only a dropped column
 * is checked (see AlterTable). The presence of the optional COLUMN keyword
 * belongs only to a column drop and is retained.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Dropping a foreign key
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t DROP FOREIGN KEY fk');
 *     [$alter->commands[0]->kind, $alter->toString()] // => [\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::ForeignKey, 'ALTER TABLE t DROP FOREIGN KEY fk']
 */
final class DropElement implements AlterCommand
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * Whether COLUMN was written; absent for other dropped elements.
     */
    public readonly ?bool $columnWritten;

    /**
     * @param ElementKind $kind The kind of element
     * @param ColumnName|null $name The element name; null only for the primary key
     * @param DropBehavior|null $behavior RESTRICT or CASCADE after a column, when written
     */
    public function __construct(public readonly ElementKind $kind, public readonly ?ColumnName $name = null, public readonly ?DropBehavior $behavior = null, ?bool $columnWritten = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(($name === null) === ($kind === ElementKind::PrimaryKey), 'Every dropped element but the primary key is named.');
        Check::input($behavior === null || $kind === ElementKind::Column, 'Only a dropped column takes RESTRICT or CASCADE.');
        Check::input($columnWritten === null || $kind === ElementKind::Column, 'Only a dropped column has an optional COLUMN keyword.');
        $this->columnWritten = $kind === ElementKind::Column ? ($columnWritten ?? true) : null;
    }

    /**
     * Derives the retained definition-name notices.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $this->name?->deriveName($derivation, $scope->relations[0]->name ?? null);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP');
        if ($this->columnWritten !== false) {
            $out->keyword(...explode(' ', $this->kind->value));
        }
        $out->node($this->name);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
