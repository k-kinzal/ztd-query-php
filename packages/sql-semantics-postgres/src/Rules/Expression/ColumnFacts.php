<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\ColumnLookup;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\LateralReach;
use SqlSemantics\Platform\PostgreSql\Rules\Resolution\Visibility;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\AmbiguousOutputName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;

/**
 * Resolves a dotted column reference and derives its facts.
 *
 * Rule: PG-COLUMN-FACTS-001. A one-part name at a position that offers
 * output columns by name refers to the one output column of that name, and
 * two such columns are ambiguous. Otherwise the last part is a column and
 * the parts before it are the relation, schema and catalog qualifiers,
 * resolved by CORE-COLUMN-LOOKUP-001; a reference that reaches a relation a
 * lateral item must not reference is reported (PG-LATERAL-JOIN-001). A one-part name that no column
 * answers to is the whole row of a visible relation of that name
 * (PG-WHOLE-ROW-001). More than four parts are an improper name.
 * Termination: one lookup through finite levels.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-COLUMN-REFS,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnFacts
{
    /**
     * Derives the facts of a column reference written with the given parts.
     *
     * @param non-empty-list<Name> $parts
     */
    public function derive(Derivation $derivation, Environment $environment, array $parts): ScalarFact
    {
        $count = count($parts);
        if ($count > 4) {
            $problem = new ImproperName(new DottedName($parts));
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }
        if ($count === 1 && $environment->aliased($parts[0]) !== []) {
            return $this->alias($derivation, $environment, $parts[0]);
        }
        $qualifier = $count === 1 ? null : (new DottedName(array_slice($parts, 0, -1)))->qualified();
        $resolution = (new ColumnLookup())->find($qualifier === null ? (new Visibility())->unqualified($environment) : $environment, $parts[$count - 1], $qualifier);
        $reach = new LateralReach();
        $barred = $reach->reached($environment, $resolution, $qualifier);
        if ($barred !== null) {
            return $reach->report($derivation, $barred);
        }
        if ($resolution instanceof ResolvedColumn) {
            return new ScalarFact($resolution->slot->type, $resolution->slot->nullability, $resolution);
        }
        if ($resolution instanceof ConditionalColumn) {
            return new ScalarFact(new Dependent($resolution->missing), Nullability::Dependent, $resolution);
        }
        if ($count === 1 && $resolution instanceof MissingColumn) {
            $row = (new WholeRows())->row($derivation, $environment, new QualifiedName($parts[0]));
            if ($row !== null) {
                return $row;
            }
        }
        Check::invariant($resolution instanceof Diagnostic, 'A column lookup resolves, depends on missing inputs, or reports a problem.');

        return new ScalarFact(new Invalid($resolution), Nullability::Dependent, $resolution);
    }

    /**
     * Derives a reference to an output column by its name.
     */
    public function alias(Derivation $derivation, Environment $environment, Name $name): ScalarFact
    {
        $fields = $environment->aliased($name);
        if (count($fields) === 1) {
            return new ScalarFact($fields[0]->type, $fields[0]->nullability, new AliasTarget($fields[0]));
        }
        $problem = new AmbiguousOutputName($name->value);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }
}
