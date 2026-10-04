<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation named by an ALTER command, with or without its inheritance descendants.
 *
 * ALTER TABLE and ALTER FOREIGN TABLE write a relation that may be limited
 * with ONLY; ALTER SEQUENCE, VIEW, MATERIALIZED VIEW and INDEX write a plain
 * relation name. The command resolves the name as a relation.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Reading the relation name
 *     $target = new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('orders'))));
 *     $target->relation->name->name->value // => 'orders'
 */
final class RelationTarget implements ObjectReference
{
    use Snapshot;

    /**
     * @param RelationReference $relation The relation name and whether ONLY is written
     */
    public function __construct(public readonly RelationReference $relation)
    {
    }

    /**
     * Derives nothing: a name holds no expression; the command resolves it.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the relation.
     */
    public function render(Output $out): void
    {
        $out->node($this->relation);
    }
}
