<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * An operator class or family named with its index access method: `name USING method`.
 *
 * Operator classes and families are named per access method.
 * Source: https://www.postgresql.org/docs/17/sql-dropopclass.html.
 *
 * @visibility public
 * @example Reading the access method
 *     $name = new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName(new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('int4_ops')]), new \SqlSemantics\Statement\Identifier\Name('btree'));
 *     $name->method->value // => 'btree'
 */
final class OperatorGroupName implements ObjectReference
{
    use Snapshot;

    /**
     * @param DottedName $name The class or family name
     * @param Name $method The index access method
     */
    public function __construct(public readonly DottedName $name, public readonly Name $method)
    {
    }

    /**
     * Derives nothing: names hold no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the name, USING and the access method.
     */
    public function render(Output $out): void
    {
        $out->node($this->name)->keyword('USING')->name($this->method, NameUse::Column);
    }
}
