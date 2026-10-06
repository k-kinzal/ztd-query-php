<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Derives the environment in which the constraint expressions of a domain are evaluated.
 *
 * Rule: PG-DOMAIN-VALUE-001. A domain constraint sees exactly one input:
 * the value being checked, written as the keyword VALUE, which the grammar
 * reads as a column reference named `value`. The value is found by that
 * unqualified name only; any other column name is missing. Its type is the
 * base type in CREATE DOMAIN and depends on the undeclared domain in ALTER
 * DOMAIN; it may be NULL. The statement that holds the constraints is the
 * relation occurrence that offers the value. A domain named with more than
 * three parts is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-createdomain.html ("the key word VALUE … refers to the value being
 * tested"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DomainInput
{
    /**
     * Records the empty row of the holder and answers the environment that sees the value.
     */
    public function environment(Derivation $derivation, Relation $holder, TypeFact $type): Environment
    {
        $fact = $derivation->relation($holder, new Environment($derivation->context));
        $value = new Name('value');

        return new Environment($derivation->context, null, [new VisibleRelation($holder, $fact->shape, null, null, [], [new ImplicitSlot([$value], new OutputSlot($value, $type, Nullability::Nullable))])]);
    }

    /**
     * Answers the type of the value of a domain that the context cannot declare.
     */
    public function undeclared(Derivation $derivation, DottedName $domain): TypeFact
    {
        $name = $domain->qualified();
        if ($name === null) {
            $problem = new CatalogMisuse(CatalogMisuseRule::ImproperName, [implode('.', array_map(static fn (Name $part): string => $part->value, $domain->parts))]);
            $derivation->report($problem);

            return new Invalid($problem);
        }

        return new Dependent([new UndeclaredDomain($name)]);
    }
}
