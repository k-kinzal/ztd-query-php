<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Json;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;

/**
 * A `name FOR ORDINALITY` column of JSON_TABLE: the number of the row, counting from 1.
 *
 * Rule: MYSQL-JSON-TABLE-COLUMN-001 (ordinality). The column is an INT
 * UNSIGNED that is never NULL at its own nesting level. Terminates: a leaf.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Reading an ordinality column
 *     (new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('n')))->name->value // => 'n'
 */
final class OrdinalityColumn implements JsonTableColumn
{
    use Snapshot;

    /**
     * @param Name $name The column name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Answers the counter column.
     *
     * @return list<OutputSlot>
     */
    public function deriveColumns(Derivation $derivation, Environment $environment): array
    {
        return [new OutputSlot($this->name, new Known(new Integral(IntegralKind::Int, null, [NumericModifier::Unsigned])), Nullability::NotNull)];
    }

    /**
     * Writes the column.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->keyword('FOR', 'ORDINALITY');
    }
}
