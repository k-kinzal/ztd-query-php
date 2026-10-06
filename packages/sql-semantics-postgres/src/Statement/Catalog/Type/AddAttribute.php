<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `ADD ATTRIBUTE name type [ COLLATE collation ] [ CASCADE | RESTRICT ]`.
 *
 * Mirrors the `AlterTableCmd` AT_AddColumn of a composite type.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility public
 * @example Reading the added attribute
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair ADD ATTRIBUTE c int8 CASCADE');
 *     $operation->statement->changes[0]->attribute->name->value // => 'c'
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
