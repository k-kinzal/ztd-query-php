<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;

/**
 * `DROP ATTRIBUTE [ IF EXISTS ] name [ CASCADE | RESTRICT ]`.
 *
 * Mirrors the `AlterTableCmd` AT_DropColumn of a composite type.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility SqlSemantics
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
