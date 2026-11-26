<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Objects with unqualified names as the objects of a GRANT or REVOKE: databases, schemas, tablespaces, languages, foreign-data wrappers or foreign servers.
 *
 * None of these is a declaration a context holds, so the names are kept
 * and not resolved.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility SqlSemantics
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
