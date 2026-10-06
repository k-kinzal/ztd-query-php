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
 * A request to rename a label of an enum type.
 *
 * Rule: PG-TYPE-ENUM-003. Mirrors `AlterEnumStmt` with `oldVal` and
 * `newVal`. A new label longer than 63 bytes is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html. Status: Implemented.
 *
 * @visibility public
 * @example Renaming a label
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER TYPE mood RENAME VALUE 'sad' TO 'blue'");
 *     $operation->toString() // => "ALTER TYPE mood RENAME VALUE 'sad' TO 'blue'"
 */
final class RenameEnumLabel implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $type The enum type
     * @param StringConstant $label The existing label
     * @param StringConstant $newLabel The new label
     */
    public function __construct(public readonly DottedName $type, public readonly StringConstant $label, public readonly StringConstant $newLabel)
    {
    }

    /**
     * Reports a new label longer than the server accepts.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new TypeChecks())->labels($derivation, [$this->newLabel]);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TYPE')->node($this->type)->keyword('RENAME', 'VALUE')->node($this->label)->keyword('TO')->node($this->newLabel);
    }
}
