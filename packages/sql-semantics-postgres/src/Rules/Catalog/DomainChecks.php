<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column\ColumnCheck;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;

/**
 * Checks the constraints written for a domain.
 *
 * Rule: PG-DOMAIN-CHECK-001. A domain accepts NULL, NOT NULL, DEFAULT and
 * CHECK constraints and a collation. UNIQUE, PRIMARY KEY, EXCLUDE and
 * REFERENCES are not possible for domains, GENERATED and IDENTITY are not
 * supported, nor is any deferrability attribute; NULL together with NOT NULL
 * conflict, a second DEFAULT is rejected, and a CHECK constraint of a domain
 * cannot be NO INHERIT. A constraint added by ALTER DOMAIN follows the same
 * rules. Termination: one pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/sql-createdomain.html, https://www.postgresql.org/docs/17/sql-alterdomain.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class DomainChecks
{
    /**
     * Reports the problems of the constraints of CREATE DOMAIN or of the one constraint ALTER DOMAIN adds.
     *
     * @param list<Clause> $constraints
     */
    public function constraints(Derivation $derivation, array $constraints): void
    {
        $kinds = [];
        foreach ($constraints as $constraint) {
            if ($constraint instanceof ConstraintAttribute) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::DomainDeferrability));
            }
            if (!$constraint instanceof Constraint) {
                continue;
            }
            $kinds[] = $constraint->kind();
            $rule = $this->rule($constraint->kind());
            if ($rule !== null) {
                $derivation->report(new CatalogMisuse($rule));
            }
            if ($constraint instanceof ColumnCheck && $constraint->noInherit) {
                $derivation->report(new CatalogMisuse(CatalogMisuseRule::DomainCheckNoInherit));
            }
        }
        if (in_array(ConstraintKind::Null, $kinds, true) && in_array(ConstraintKind::NotNull, $kinds, true)) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::DomainNullConflict));
        }
        if (count(array_keys($kinds, ConstraintKind::Default, true)) > 1) {
            $derivation->report(new CatalogMisuse(CatalogMisuseRule::DomainDefaults));
        }
    }

    /**
     * Answers the rule a constraint of a kind breaks when written for a domain, or null when a domain accepts it.
     */
    public function rule(ConstraintKind $kind): ?CatalogMisuseRule
    {
        return match ($kind) {
            ConstraintKind::Null, ConstraintKind::NotNull, ConstraintKind::Default, ConstraintKind::Check => null,
            ConstraintKind::Identity, ConstraintKind::Generated => CatalogMisuseRule::DomainGenerated,
            ConstraintKind::PrimaryKey => CatalogMisuseRule::DomainPrimaryKey,
            ConstraintKind::Unique => CatalogMisuseRule::DomainUnique,
            ConstraintKind::Exclusion => CatalogMisuseRule::DomainExclusion,
            ConstraintKind::ForeignKey => CatalogMisuseRule::DomainForeignKey,
        };
    }

    /**
     * Reports the attributes a constraint written with `ConstraintAttributeSpec` cannot carry.
     *
     * Deferral is rejected for CHECK and NOT NULL, NOT VALID for NOT NULL,
     * and NO INHERIT for the CHECK constraint of a domain; NOT DEFERRABLE and
     * INITIALLY IMMEDIATE state the defaults and are accepted.
     *
     * @param list<ConstraintAttribute> $attributes
     */
    public function attributes(Derivation $derivation, array $attributes, bool $check): void
    {
        $type = $check ? 'CHECK' : 'NOT NULL';
        foreach ($attributes as $attribute) {
            $rule = match ($attribute) {
                ConstraintAttribute::Deferrable, ConstraintAttribute::InitiallyDeferred => CatalogMisuseRule::MarkedDeferrable,
                ConstraintAttribute::NotValid => $check ? null : CatalogMisuseRule::MarkedNotValid,
                ConstraintAttribute::NoInherit => $check ? CatalogMisuseRule::DomainCheckNoInherit : null,
                ConstraintAttribute::NotDeferrable, ConstraintAttribute::InitiallyImmediate => null,
            };
            if ($rule !== null) {
                $derivation->report(new CatalogMisuse($rule, $rule === CatalogMisuseRule::DomainCheckNoInherit ? [] : [$type]));

                return;
            }
        }
    }
}
