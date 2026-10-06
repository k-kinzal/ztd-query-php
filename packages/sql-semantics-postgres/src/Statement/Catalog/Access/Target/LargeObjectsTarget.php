<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\ObjectIdentifiers;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * Large objects named by their identifiers as the objects of a GRANT or REVOKE.
 *
 * The grammar accepts any signed number; the server reads each as an
 * object identifier when the statement runs, and the statement reports a
 * number that is no object identifier (PG-LARGE-OBJECT-OID-001).
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the identifier of a large object
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON LARGE OBJECT 16385 TO joe');
 *     $operation->statement->target->identifiers[0]->magnitude->digits // => '16385'
 */
final class LargeObjectsTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * @var non-empty-list<SignedNumber> The identifiers in the order written
     */
    public readonly array $identifiers;

    /**
     * @param list<SignedNumber> $identifiers The identifiers in the order written, at least one
     */
    public function __construct(array $identifiers)
    {
        $this->identifiers = Check::listOf($identifiers, SignedNumber::class, 'A grant names at least one large object.', 1);
    }

    /**
     * Answers the kind of the objects: large objects.
     */
    public function object(): PrivilegeObjectKind
    {
        return PrivilegeObjectKind::LargeObject;
    }

    /**
     * Reports the numbers the server cannot read as object identifiers.
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void
    {
        (new ObjectIdentifiers())->check($derivation, $this->identifiers);
    }

    /**
     * Writes the kind and the identifiers.
     */
    public function render(Output $out): void
    {
        $out->keyword('LARGE', 'OBJECT')->list($this->identifiers);
    }
}
