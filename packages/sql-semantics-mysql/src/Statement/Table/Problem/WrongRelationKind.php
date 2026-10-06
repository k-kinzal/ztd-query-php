<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A name whose declaration is a base table where the statement needs a view, or a view where it needs a base table.
 *
 * The server refuses the statement (MYSQL-RELATION-KIND-001).
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Describing a base table that a view statement names
 *     (new \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('shop')), \SqlSemantics\Platform\MySql\Statement\Table\Problem\KindRefusal::NotView))->message() // => 'shop.t is not VIEW.'
 */
final class WrongRelationKind implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     * @param KindRefusal $refusal What the server reports
     */
    public function __construct(public readonly QualifiedName $name, public readonly KindRefusal $refusal)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return ($this->name->schema === null ? '' : $this->name->schema->value . '.') . $this->name->name->value . ' ' . $this->refusal->value . '.';
    }
}
