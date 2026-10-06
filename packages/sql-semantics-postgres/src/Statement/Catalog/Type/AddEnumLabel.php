<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\TypeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to add a label to an enum type: `ALTER TYPE name ADD VALUE [ IF NOT EXISTS ] 'label' [ BEFORE | AFTER 'label' ]`.
 *
 * Rule: PG-TYPE-ENUM-002. Mirrors `AlterEnumStmt` with `newVal`,
 * `newValNeighbor`, `newValIsAfter` and `skipIfNewValExists`. A label longer
 * than 63 bytes is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html. Status: Implemented.
 *
 * @visibility public
 * @example Adding a label before another
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER TYPE mood ADD VALUE IF NOT EXISTS 'calm' BEFORE 'happy'");
 *     [$operation->statement->label->value, $operation->statement->position?->after] // => ['calm', false]
 */
final class AddEnumLabel implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $type The enum type
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param StringConstant $label The new label
     * @param EnumPosition|null $position Where the label goes, when written
     */
    public function __construct(public readonly DottedName $type, public readonly bool $ifNotExists, public readonly StringConstant $label, public readonly ?EnumPosition $position = null)
    {
    }

    /**
     * Reports a label longer than the server accepts.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new TypeChecks())->labels($derivation, [$this->label]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TYPE')->node($this->type)->keyword('ADD', 'VALUE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->node($this->label)->node($this->position);
    }
}
