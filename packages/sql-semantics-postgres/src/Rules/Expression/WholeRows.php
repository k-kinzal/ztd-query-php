<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousRelation;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Resolves a relation name used as a value: the whole row of a visible relation.
 *
 * Rule: PG-WHOLE-ROW-001. The name is matched against the relations visible
 * at the position and then at the enclosing query levels, nearest first: an
 * alias by its name alone, an unaliased relation by its name and, when
 * written, its schema. The nearest level with a match decides; two matches
 * there are ambiguous; a relation a lateral item must not reference is
 * reported (PG-LATERAL-JOIN-001). Facts: the row type of the relation, whose fields are
 * its columns, or a dependence on the missing inputs of an open shape; a
 * row of an outer join's NULL side is NULL, so the value can be NULL.
 * Termination: the chain of levels and each relation list are finite.
 * Source: https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-USAGE,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-COLUMN-REFS. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class WholeRows
{
    /**
     * Derives the whole row a `name.*` reference stands for; a relation that is not visible is reported.
     *
     * @param non-empty-list<Name> $qualifiers
     */
    public function star(Derivation $derivation, Environment $environment, array $qualifiers): ScalarFact
    {
        $name = (new DottedName($qualifiers))->qualified();
        if ($name === null) {
            $problem = new ImproperName(new DottedName($qualifiers));
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }
        $fact = $this->row($derivation, $environment, $name);
        if ($fact !== null) {
            return $fact;
        }
        $problem = new MissingTable($name);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Derives the whole row of the visible relation a name matches, or answers null when none matches.
     */
    public function row(Derivation $derivation, Environment $environment, QualifiedName $name): ?ScalarFact
    {
        $found = $this->find($environment, $name);
        if ($found === []) {
            return null;
        }
        if (count($found) > 1) {
            $problem = new AmbiguousRelation($name->name->value);
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }
        $relation = $found[0];
        $reach = new LateralReach();
        if ($reach->barred($relation)) {
            return $reach->report($derivation, $relation);
        }
        if (!$relation->shape->complete()) {
            return new ScalarFact(new Dependent($relation->shape->missing), Nullability::Dependent);
        }

        return new ScalarFact(new Known(new Composite($relation->shape->slots, $relation->alias ?? $relation->name?->name)), Nullability::Nullable);
    }

    /**
     * Answers the relations of the nearest level that the name matches.
     *
     * @return list<VisibleRelation>
     */
    public function find(Environment $environment, QualifiedName $name): array
    {
        $names = $environment->context->relationNames;
        for ($scope = $environment; $scope !== null; $scope = $scope->outer) {
            $found = [];
            foreach ($scope->relations as $relation) {
                if ($relation->alias !== null) {
                    $match = $name->schema === null && $names->equal($relation->alias->value, $name->name->value);
                } else {
                    $match = $relation->name !== null && $names->equal($relation->name->name->value, $name->name->value)
                        && ($name->schema === null || $relation->name->schema === null || $names->equal($relation->name->schema->value, $name->schema->value));
                }
                if ($match) {
                    $found[] = $relation;
                }
            }
            if ($found !== []) {
                return $found;
            }
        }

        return [];
    }
}
