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
 * Every object of one kind in the named schemas as the objects of a GRANT or REVOKE, such as ALL TABLES IN SCHEMA.
 *
 * Mirrors the target type `ACL_TARGET_ALL_IN_SCHEMA`. Which objects the
 * schemas hold is known when the statement runs, so nothing is resolved.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the schemas whose tables are granted
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON ALL TABLES IN SCHEMA app TO joe');
 *     [$operation->statement->target->object()->value, $operation->statement->target->schemas[0]->value] // => ['TABLE', 'app']
 */
final class SchemaContentsTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * The kinds that can be granted for whole schemas.
     */
    public const KINDS = [PrivilegeObjectKind::Relation, PrivilegeObjectKind::Sequence, PrivilegeObjectKind::Function, PrivilegeObjectKind::Procedure, PrivilegeObjectKind::Routine];

    /**
     * @var non-empty-list<Name> The schemas in the order written
     */
    public readonly array $schemas;

    /**
     * @param PrivilegeObjectKind $object The kind: tables, sequences, functions, procedures or routines
     * @param list<Name> $schemas The schemas in the order written, at least one
     */
    public function __construct(public readonly PrivilegeObjectKind $object, array $schemas)
    {
        Check::input(in_array($object, self::KINDS, true), 'The kind can be granted for whole schemas.');
        $this->schemas = Check::listOf($schemas, Name::class, 'A grant names at least one schema.', 1);
    }

    /**
     * Answers the kind of the objects.
     */
    public function object(): PrivilegeObjectKind
    {
        return $this->object;
    }

    /**
     * Derives nothing: the objects of the schemas are known when the statement runs.
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void
    {
    }

    /**
     * Writes ALL, the plural of the kind, IN SCHEMA and the schemas.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALL', $this->object->value . 'S', 'IN', 'SCHEMA');
        foreach ($this->schemas as $position => $schema) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($schema, NameUse::Column);
        }
    }
}
