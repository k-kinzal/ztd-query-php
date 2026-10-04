<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\GrantedRelations;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * Relations or sequences named as the objects of a GRANT or REVOKE.
 *
 * The word TABLE before relations is optional and always written back.
 * Each relation is resolved against the context and its column privileges
 * are checked (PG-GRANT-RELATION-001); a sequence is not a declaration a
 * context holds and is not resolved.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the table a privilege is granted on
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $table = $semantics->analyze('CREATE TABLE items (id int4, price int4)');
 *     $operation = $semantics->analyze('GRANT SELECT ON items TO joe', [$table]);
 *     $operation->facts->relation($operation->statement->target->relations[0])->table->table === $table->declarations()[0] // => true
 */
final class RelationsTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * @var non-empty-list<RelationReference> The relations in the order written
     */
    public readonly array $relations;

    /**
     * @param PrivilegeObjectKind $object The kind: relations or sequences
     * @param list<RelationReference> $relations The relations in the order written, at least one, none limited with ONLY
     */
    public function __construct(public readonly PrivilegeObjectKind $object, array $relations)
    {
        Check::input($object === PrivilegeObjectKind::Relation || $object === PrivilegeObjectKind::Sequence, 'Qualified names are relations or sequences.');
        $this->relations = Check::listOf($relations, RelationReference::class, 'A grant names at least one relation.', 1);
        foreach ($this->relations as $relation) {
            Check::input(!$relation->only, 'A grant names a relation without ONLY.');
        }
    }

    /**
     * Answers the kind of the objects.
     */
    public function object(): PrivilegeObjectKind
    {
        return $this->object;
    }

    /**
     * Resolves each relation and checks its column privileges; sequences are not resolved.
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void
    {
        if ($this->object === PrivilegeObjectKind::Relation) {
            foreach ($this->relations as $relation) {
                (new GrantedRelations())->derive($derivation, $relation, $privileges);
            }
        }
    }

    /**
     * Writes the kind and the names.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->object->value)->list($this->relations);
    }
}
