<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `DROP ATTRIBUTE [ IF EXISTS ] name [ CASCADE | RESTRICT ]`.
 *
 * Mirrors the `AlterTableCmd` AT_DropColumn of a composite type.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility public
 * @example Dropping an attribute if it exists
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair DROP ATTRIBUTE IF EXISTS b');
 *     $operation->statement->changes[0]->ifExists // => true
 */
final class DropAttribute implements AttributeChange
{
    use Snapshot;

    /**
     * @param Name $name The attribute name
     * @param bool $ifExists Whether IF EXISTS is written
     * @param DropBehavior|null $behavior Whether typed tables are changed too, when written
     */
    public function __construct(public readonly Name $name, public readonly bool $ifExists = false, public readonly ?DropBehavior $behavior = null)
    {
    }

    /**
     * Derives nothing: the change holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the change.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'ATTRIBUTE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Column);
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
