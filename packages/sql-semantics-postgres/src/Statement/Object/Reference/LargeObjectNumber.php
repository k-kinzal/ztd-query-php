<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A large object named by its object identifier.
 *
 * Source: https://www.postgresql.org/docs/17/sql-alterlargeobject.html.
 *
 * @visibility public
 * @example Reading the identifier
 *     $object = new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\LargeObjectNumber(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber(false, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('16400')));
 *     $object->identifier->magnitude->digits // => '16400'
 */
final class LargeObjectNumber implements ObjectReference
{
    use Snapshot;

    /**
     * @param SignedNumber $identifier The object identifier as written
     */
    public function __construct(public readonly SignedNumber $identifier)
    {
    }

    /**
     * Derives nothing: a number holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the identifier.
     */
    public function render(Output $out): void
    {
        $out->node($this->identifier);
    }
}
