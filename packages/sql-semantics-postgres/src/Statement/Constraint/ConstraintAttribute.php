<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * A property written after a constraint: whether it can be deferred, when it is checked, and whether it is validated or inherited.
 *
 * Mirrors the `CONSTR_ATTR_*` values of PostgreSQL's `Constraint` node and the
 * `CAS_*` bits of `ConstraintAttributeSpec`. NOT DEFERRABLE and INITIALLY
 * IMMEDIATE are the defaults; a written default is kept as written.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the attributes of a table constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, UNIQUE (a) DEFERRABLE INITIALLY DEFERRED)');
 *     $create->statement->definition->elements[1]->attributes // => [\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::Deferrable, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::InitiallyDeferred]
 */
enum ConstraintAttribute: string implements Clause
{
    case Deferrable = 'DEFERRABLE';
    case NotDeferrable = 'NOT DEFERRABLE';
    case InitiallyDeferred = 'INITIALLY DEFERRED';
    case InitiallyImmediate = 'INITIALLY IMMEDIATE';
    case NotValid = 'NOT VALID';
    case NoInherit = 'NO INHERIT';

    /**
     * Tells whether the attribute can be written after a column constraint, where only the deferral attributes are allowed.
     */
    public function deferral(): bool
    {
        return match ($this) {
            self::Deferrable, self::NotDeferrable, self::InitiallyDeferred, self::InitiallyImmediate => true,
            self::NotValid, self::NoInherit => false,
        };
    }

    /**
     * Derives nothing: an attribute has no operand.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the keywords.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->value));
    }
}
