<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorArity;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\OperatorSignature;
use SqlSemantics\Statement\Fact\RelationFact;

/**
 * Derives the objects a generic object command names.
 *
 * Rule: PG-OBJECT-FACTS-001. Every object reference derives the type names
 * and signatures it holds in the statement environment; a relation it names
 * is resolved by PG-OBJECT-RELATION-001. An operator written `(type, NONE)`
 * names a postfix operator, which no longer exists, so a command without IF
 * EXISTS that names one is reported. Routines, operators, types and the other
 * catalog objects cannot be declared in the context and are not resolved.
 * Terminates: one pass over the references.
 * Source: https://www.postgresql.org/docs/17/sql-dropoperator.html,
 * https://www.postgresql.org/docs/17/release-14.html (postfix operators removed). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ObjectFacts
{
    /**
     * Derives every reference and answers the relation fact of each, null where the kind names no relation.
     *
     * @param list<ObjectReference> $objects
     *
     * @return list<RelationFact|null>
     */
    public function derive(ObjectKind $kind, array $objects, bool $ifExists, Derivation $derivation): array
    {
        $environment = $derivation->environment();
        $facts = [];
        foreach ($objects as $object) {
            $object->deriveClause($derivation, $environment);
            if (!$ifExists && $object instanceof OperatorSignature && $object->arity === OperatorArity::Postfix) {
                $derivation->report(new RoutineProblem(RoutineProblemKind::PostfixOperator));
            }
            $facts[] = (new RelationTargets())->resolve($kind, $object, $ifExists, $derivation);
        }

        return $facts;
    }
}
