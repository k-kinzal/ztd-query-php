<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A column given by name, type and optional collation, as a composite type or a column definition list writes it.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM.
 *
 * @visibility SqlSemantics
 * @example Reading a typed column
 *     $column = new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn(
 *         new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::Integer)),
 *     );
 *     [$column->name->value, $column->collation] // => ['a', null]
 */
final class TypedColumn implements Clause
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param TypeName $type The column type
     * @param DottedName|null $collation The collation of the column
     */
    public function __construct(public readonly Name $name, public readonly TypeName $type, public readonly ?DottedName $collation = null)
    {
    }

    /**
     * Derives the modifier expressions of the type.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name, the type and the collation.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->node($this->type);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
    }
}
