<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Domain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A NOT NULL constraint added to a domain in release 17: `[ CONSTRAINT name ] NOT NULL attributes`.
 *
 * Mirrors the `Constraint` of kind CONSTR_NOTNULL that `DomainConstraintElem`
 * builds. Deferral and NOT VALID are diagnostics (PG-DOMAIN-CHECK-001).
 * Source: https://www.postgresql.org/docs/17/sql-alterdomain.html.
 *
 * @visibility public
 * @example Adding a named not-null constraint
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD CONSTRAINT nn NOT NULL');
 *     $operation->statement->constraint->name?->value // => 'nn'
 */
final class DomainNotNull implements Clause
{
    use Snapshot;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param Name|null $name The constraint name, when CONSTRAINT is written
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     */
    public function __construct(public readonly ?Name $name = null, array $attributes = [])
    {
        $this->attributes = Check::listOf($attributes, ConstraintAttribute::class, 'Constraint attributes are a list.');
    }

    /**
     * Checks the attributes.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new DomainChecks())->attributes($derivation, $this->attributes, false);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        if ($this->name !== null) {
            $out->keyword('CONSTRAINT')->name($this->name, NameUse::Column);
        }
        $out->keyword('NOT', 'NULL');
        foreach ($this->attributes as $attribute) {
            $out->node($attribute);
        }
    }
}
