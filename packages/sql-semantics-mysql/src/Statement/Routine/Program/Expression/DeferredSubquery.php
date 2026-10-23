<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored value or existence use of a query with an intrinsic read-column constraint.
 *
 * The complete query remains available for invocation-time dependency checks.
 * It cannot supply an ordinary Scalar, but an unreached stored instruction may
 * retain it. Empty or inconsistent written rows, INSERT-only defaults and a
 * tableless star differ from a catalog-dependent wildcard width.
 *
 * @visibility public
 * @example Keeping a zero-column subquery outside the scalar domain
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubquery($query) instanceof \SqlSemantics\Statement\Scalar // => false
 * @example Refusing an ordinary one-column query in the deferred role
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredSubquery($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing zero columns in an ordinary scalar subquery
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredSubquery implements DeferredOperand
{
    use Snapshot;

    /**
     * @param Query $query The complete query with an intrinsic read-column constraint
     * @param bool $exists Whether only row existence is tested, allowing a tableless star
     */
    public function __construct(public readonly Query $query, public readonly bool $exists = false)
    {
        Check::input((new ReadColumns())->problem($query, $exists) !== null, 'A deferred subquery value retains an intrinsic read-column constraint.');
        (new SubqueryOptions())->check($query);
        (new Generations())->check($this);
    }

    /**
     * Binds query dependencies without assigning an ordinary scalar role.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->exists) {
            (new \SqlSemantics\Platform\MySql\Rules\Query\Existence())->mark($this->query, $derivation);
        }
        $derivation->query($this->query, $environment);

        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * The dependency is a Query, retained separately from scalar operands.
     *
     * @return list<never>
     */
    public function operands(): array
    {
        return [];
    }

    /**
     * Answers the fixed read-column condition; earlier dependencies may fail first.
     */
    public function problem(): Diagnostic
    {
        $problem = (new ReadColumns())->problem($this->query, $this->exists);
        Check::invariant($problem !== null, 'A deferred subquery value retains its read-column constraint.');
        return $problem;
    }

    /**
     * Writes the complete subquery in its value position.
     */
    public function render(Output $out): void
    {
        if ($this->exists) {
            $out->keyword('EXISTS');
        }
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
