<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * Objects with unqualified names as the objects of a GRANT or REVOKE: databases, schemas, tablespaces, languages, foreign-data wrappers or foreign servers.
 *
 * None of these is a declaration a context holds, so the names are kept
 * and not resolved.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the schemas of a grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT USAGE ON SCHEMA app, audit TO joe');
 *     $operation->statement->target->names[1]->value // => 'audit'
 */
final class NamedObjectsTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * The kinds whose objects have unqualified names.
     */
    public const KINDS = [PrivilegeObjectKind::Database, PrivilegeObjectKind::Schema, PrivilegeObjectKind::Tablespace, PrivilegeObjectKind::Language, PrivilegeObjectKind::ForeignDataWrapper, PrivilegeObjectKind::ForeignServer];

    /**
     * @var non-empty-list<Name> The names in the order written
     */
    public readonly array $names;

    /**
     * @param PrivilegeObjectKind $object The kind, one whose objects have unqualified names
     * @param list<Name> $names The names in the order written, at least one
     */
    public function __construct(public readonly PrivilegeObjectKind $object, array $names)
    {
        Check::input(in_array($object, self::KINDS, true), 'The kind has objects with unqualified names.');
        $this->names = Check::listOf($names, Name::class, 'A grant names at least one object.', 1);
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
        $out->keyword(...explode(' ', $this->object->value));
        foreach ($this->names as $position => $name) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($name, NameUse::Column);
        }
    }
}
