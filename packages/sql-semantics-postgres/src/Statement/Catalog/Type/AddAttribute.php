<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;

/**
 * `ADD ATTRIBUTE name type [ COLLATE collation ] [ CASCADE | RESTRICT ]`.
 *
 * Mirrors the `AlterTableCmd` AT_AddColumn of a composite type.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility SqlSemantics
 */
final class AddAttribute implements AttributeChange
{
    use Snapshot;

    /**
     * @param TypedColumn $attribute The new attribute
     * @param DropBehavior|null $behavior Whether typed tables are changed too, when written
     */
    public function __construct(public readonly TypedColumn $attribute, public readonly ?DropBehavior $behavior = null)
    {
    }

    /**
     * Derives the attribute type.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->attribute->deriveClause($derivation, $environment);
    }

    /**
     * Writes the change.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD', 'ATTRIBUTE')->node($this->attribute);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
