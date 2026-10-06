<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove a label from an enum type, which the server rejects.
 *
 * Rule: PG-TYPE-ENUM-004. The grammar of release 17 accepts
 * `ALTER TYPE name DROP VALUE 'label'` only to report that dropping an enum
 * value is not implemented; the request is kept and the rejection is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/datatype-enum.html#DATATYPE-ENUM-IMPLEMENTATION-DETAILS
 * ("Existing values cannot be removed from an enum type"). Status: Implemented.
 *
 * @visibility public
 * @example Reading the rejected request
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("ALTER TYPE mood DROP VALUE 'sad'");
 *     [$operation->statement->label->value, $operation->facts->diagnostics[0]->message()] // => ['sad', 'dropping an enum value is not implemented']
 */
final class DropEnumLabel implements Statement
{
    use Snapshot;

    /**
     * @param DottedName $type The enum type
     * @param StringConstant $label The label
     */
    public function __construct(public readonly DottedName $type, public readonly StringConstant $label)
    {
    }

    /**
     * Reports that the server does not implement the request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->report(new CatalogMisuse(CatalogMisuseRule::EnumValueDrop));
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TYPE')->node($this->type)->keyword('DROP', 'VALUE')->node($this->label);
    }
}
