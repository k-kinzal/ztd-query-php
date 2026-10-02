<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A table named with or without its inheritance descendants, as `relation_expr` writes it.
 *
 * Mirrors PostgreSQL's `RangeVar` name and `inh` flag. A table stands for
 * itself and its descendant tables unless ONLY is written; a trailing `*`
 * states the default explicitly and changes nothing.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM.
 *
 * @visibility public
 * @example Naming one table without its descendants
 *     $table = new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), true);
 *     [$table->name->name->value, $table->only] // => ['t', true]
 */
final class RelationReference implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $name The relation name
     * @param bool $only Whether descendant tables are excluded
     */
    public function __construct(public readonly QualifiedName $name, public readonly bool $only = false)
    {
    }

    /**
     * Writes ONLY when descendants are excluded, then the name.
     */
    public function render(Output $out): void
    {
        if ($this->only) {
            $out->keyword('ONLY');
        }
        (new Spelling())->qualified($out, $this->name);
    }
}
