<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * ALTER [ COLUMN ] ... [ SET DATA ] TYPE: changes the type of a column.
 *
 * Mirrors `AT_AlterColumnType` with a `ColumnDef` holding the type, the collation and the USING expression
 * (`raw_default`). SET DATA changes nothing and is not kept. The USING expression is derived where the
 * columns of the relation are visible; it computes the new value from the old row.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class TypeChange implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $column The column
     * @param TypeName $type The new type
     * @param DottedName|null $collation The collation
     * @param Scalar|null $using The conversion expression
     */
    public function __construct(
        public readonly Name $column,
        public readonly TypeName $type,
        public readonly ?DottedName $collation = null,
        public readonly ?Scalar $using = null,
    ) {
    }

    /**
     * Checks that the column exists and derives the type and the conversion.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
        $this->type->deriveClause($derivation, $environment);
        if ($this->using !== null) {
            $derivation->scalar($this->using, $environment);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword('TYPE')->node($this->type);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
        if ($this->using !== null) {
            $out->keyword('USING')->node($this->using);
        }
    }
}
