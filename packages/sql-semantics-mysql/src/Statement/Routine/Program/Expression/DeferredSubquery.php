<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Shape\QueryWidth;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\SubqueryOptions;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored value use of a query whose first written row has no columns.
 *
 * The complete query remains available for invocation-time dependency checks.
 * It cannot supply an ordinary Scalar, but an unreached stored instruction may
 * retain it. A zero-column output is distinct from an unknown catalog width.
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
     * @param Query $query The complete query with a fixed zero-column output
     */
    public function __construct(public readonly Query $query)
    {
        Check::input((new QueryWidth())->of($query) === 0, 'A deferred subquery value retains a fixed zero-column output.');
        (new SubqueryOptions())->check($query);
        (new Generations())->check($this);
    }

    /**
     * Binds query dependencies without assigning an ordinary scalar role.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
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
     * Answers the missing-column condition; earlier query dependencies may fail first.
     */
    public function problem(): Diagnostic
    {
        return new Misuse(MisuseRule::EmptyValuesRow);
    }

    /**
     * Writes the complete subquery in its value position.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->node($this->query)->symbol(')');
    }
}
