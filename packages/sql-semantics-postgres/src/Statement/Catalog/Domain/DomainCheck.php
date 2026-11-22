<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;

/**
 * A CHECK constraint added to a domain in release 17: `[ CONSTRAINT name ] CHECK ( expression ) attributes`.
 *
 * Mirrors the `Constraint` of kind CONSTR_CHECK that `DomainConstraintElem`
 * builds. NOT VALID skips checking existing values; deferral and NO INHERIT
 * are diagnostics (PG-DOMAIN-CHECK-001).
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html.
 *
 * @visibility SqlSemantics
 */
final class DomainCheck implements Clause
{
    use Snapshot;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param Name|null $name The constraint name, when CONSTRAINT is written
     * @param Scalar $condition The condition
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     */
    public function __construct(public readonly ?Name $name, public readonly Scalar $condition, array $attributes = [])
    {
        $this->attributes = Check::listOf($attributes, ConstraintAttribute::class, 'Constraint attributes are a list.');
    }

    /**
     * Derives the condition where VALUE is visible and checks the attributes.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->condition, $environment);
        (new DomainChecks())->attributes($derivation, $this->attributes, true);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        if ($this->name !== null) {
            $out->keyword('CONSTRAINT')->name($this->name, NameUse::Column);
        }
        $out->keyword('CHECK')->symbol('(')->node($this->condition)->symbol(')');
        foreach ($this->attributes as $attribute) {
            $out->node($attribute);
        }
    }
}
