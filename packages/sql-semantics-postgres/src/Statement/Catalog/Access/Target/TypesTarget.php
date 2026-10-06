<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * Types or domains named as the objects of a GRANT or REVOKE.
 *
 * The names are dotted names, not type designations: `int` or `varchar(3)`
 * cannot be written here. User types are not declarations a context holds,
 * so the names are kept and not resolved.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the domain of a grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT USAGE ON DOMAIN app.email TO joe');
 *     $operation->statement->target->names[0]->last()->value // => 'email'
 */
final class TypesTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * @var non-empty-list<DottedName> The names in the order written
     */
    public readonly array $names;

    /**
     * @param PrivilegeObjectKind $object The kind: types or domains
     * @param list<DottedName> $names The names in the order written, at least one
     */
    public function __construct(public readonly PrivilegeObjectKind $object, array $names)
    {
        Check::input($object === PrivilegeObjectKind::Type || $object === PrivilegeObjectKind::Domain, 'Dotted names are types or domains.');
        $this->names = Check::listOf($names, DottedName::class, 'A grant names at least one type.', 1);
    }

    /**
     * Answers the kind of the objects.
     */
    public function object(): PrivilegeObjectKind
    {
        return $this->object;
    }

    /**
     * Derives nothing: the names are not resolved.
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void
    {
    }

    /**
     * Writes the kind and the names.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->object->value)->list($this->names);
    }
}
