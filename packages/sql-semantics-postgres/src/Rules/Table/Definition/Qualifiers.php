<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnCheck;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnUnique;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\DefaultExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Generated;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\Identity;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NotNull;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\NullAllowed;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\References;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ColumnCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Checks and derives the qualifiers of a column: its constraints, deferral attributes and collation.
 *
 * Rule: PG-COLUMN-QUALIFIERS-001. A qualifier list holds column constraints,
 * the four deferral attributes and COLLATE clauses, in the order written.
 * Each constraint is derived in the environment of the table. The server
 * reports (`SplitColQualList`, `transformConstraintAttrs`,
 * `transformColumnDefinition`): more than one COLLATE; a deferral attribute
 * that does not follow a UNIQUE, PRIMARY KEY or REFERENCES constraint
 * ("misplaced ... clause"); two DEFERRABLE/NOT DEFERRABLE or two INITIALLY
 * attributes for one constraint; INITIALLY DEFERRED on a constraint marked
 * NOT DEFERRABLE; NULL and NOT NULL together; more than one DEFAULT,
 * identity or generation clause; and any two of DEFAULT, identity and
 * generation. Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 * Termination: one pass over the qualifiers. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Qualifiers
{
    /**
     * The column constraint classes.
     */
    private const CONSTRAINTS = [NotNull::class, NullAllowed::class, ColumnUnique::class, ColumnPrimaryKey::class, ColumnCheck::class, DefaultExpression::class, Identity::class, Generated::class, References::class];

    /**
     * Checks that a list holds column constraints, deferral attributes and collations.
     *
     * @param array<array-key, object|scalar|null> $qualifiers
     * @return list<Clause>
     */
    public function checked(array $qualifiers): array
    {
        $checked = [];
        foreach (Check::listOf($qualifiers, Clause::class, 'Column qualifiers are an ordered list of clauses.') as $qualifier) {
            $admitted = $qualifier instanceof ColumnCollation
                || ($qualifier instanceof ConstraintAttribute && $qualifier->deferral())
                || in_array($qualifier::class, self::CONSTRAINTS, true);
            Check::input($admitted, 'A column qualifier is a column constraint, a deferral attribute or a collation.');
            $checked[] = $qualifier;
        }

        return $checked;
    }

    /**
     * Derives the qualifiers and reports their problems.
     *
     * @param list<Clause> $qualifiers
     */
    public function derive(Derivation $derivation, Environment $environment, Name $column, array $qualifiers): void
    {
        $collations = 0;
        foreach ($qualifiers as $qualifier) {
            $qualifier->deriveClause($derivation, $environment);
            $collations += $qualifier instanceof ColumnCollation ? 1 : 0;
        }
        if ($collations > 1) {
            $derivation->report(new DefinitionProblem(DefinitionRule::MultipleCollations));
        }
        $this->attributes($derivation, $qualifiers);
        $this->conflicts($derivation, $column, $qualifiers);
        (new GeneratedColumns())->columnKeys($derivation, $qualifiers);
    }

    /**
     * Reports misplaced, repeated and contradictory deferral attributes.
     *
     * @param list<Clause> $qualifiers
     */
    public function attributes(Derivation $derivation, array $qualifiers): void
    {
        $owner = null;
        $deferrability = null;
        $timing = null;
        foreach ($qualifiers as $qualifier) {
            if ($qualifier instanceof ColumnCollation) {
                continue;
            }
            if (!$qualifier instanceof ConstraintAttribute) {
                $owner = $qualifier;
                $deferrability = null;
                $timing = null;
                continue;
            }
            if (!$owner instanceof ColumnUnique && !$owner instanceof ColumnPrimaryKey && !$owner instanceof References) {
                $derivation->report(new DefinitionProblem(DefinitionRule::MisplacedAttribute, new Name($qualifier->value)));
                continue;
            }
            $deferral = $qualifier === ConstraintAttribute::Deferrable || $qualifier === ConstraintAttribute::NotDeferrable;
            if (($deferral ? $deferrability : $timing) !== null) {
                $derivation->report(new DefinitionProblem($deferral ? DefinitionRule::MultipleDeferrability : DefinitionRule::MultipleTiming));
            }
            if ($deferral) {
                $deferrability = $qualifier;
            } else {
                $timing = $qualifier;
            }
            if ($deferrability === ConstraintAttribute::NotDeferrable && $timing === ConstraintAttribute::InitiallyDeferred) {
                $derivation->report(new DefinitionProblem(DefinitionRule::DeferredNotDeferrable));
            }
        }
    }

    /**
     * Reports contradictory NULL facts and repeated or combined value sources.
     *
     * @param list<Clause> $qualifiers
     */
    public function conflicts(Derivation $derivation, Name $column, array $qualifiers): void
    {
        $nullable = null;
        $counts = [DefaultExpression::class => 0, Identity::class => 0, Generated::class => 0];
        foreach ($qualifiers as $qualifier) {
            if ($qualifier instanceof NotNull || $qualifier instanceof NullAllowed) {
                if ($nullable !== null && $nullable !== $qualifier instanceof NullAllowed) {
                    $derivation->report(new DefinitionProblem(DefinitionRule::ConflictingNullability, $column));
                }
                $nullable = $qualifier instanceof NullAllowed;
            }
            if (isset($counts[$qualifier::class])) {
                $counts[$qualifier::class]++;
            }
        }
        $rules = [
            [$counts[DefaultExpression::class] > 1, DefinitionRule::MultipleDefaults],
            [$counts[Identity::class] > 1, DefinitionRule::MultipleIdentities],
            [$counts[Generated::class] > 1, DefinitionRule::MultipleGenerations],
            [$counts[DefaultExpression::class] > 0 && $counts[Identity::class] > 0, DefinitionRule::DefaultAndIdentity],
            [$counts[DefaultExpression::class] > 0 && $counts[Generated::class] > 0, DefinitionRule::DefaultAndGeneration],
            [$counts[Identity::class] > 0 && $counts[Generated::class] > 0, DefinitionRule::IdentityAndGeneration],
        ];
        foreach ($rules as [$broken, $rule]) {
            if ($broken) {
                $derivation->report(new DefinitionProblem($rule, $column));
            }
        }
    }
}
