<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The grouping operation `GROUPING(a, b, …)`: a bit mask telling which arguments the current grouping set leaves out.
 *
 * Mirrors PostgreSQL's `GroupingFunc` node.
 *
 * Rule: PG-GROUPING-001. Facts: `integer`, never NULL; an unaliased result
 * column is named `grouping`. Whether each argument matches a grouping
 * expression is decided by the query that groups. Source:
 * https://www.postgresql.org/docs/17/functions-aggregate.html#FUNCTIONS-GROUPING-TABLE. Status: Implemented.
 *
 * @visibility public
 * @example Reading the arguments of GROUPING
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT GROUPING(1, 2)');
 *     [count($query->field(0)->expression->arguments), $query->field(0)->name->value] // => [2, 'grouping']
 */
final class GroupingFunction implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var non-empty-list<Scalar> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param list<Scalar> $arguments The arguments in order; at least one
     */
    public function __construct(array $arguments)
    {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'GROUPING takes at least one expression.', 1);
    }

    /**
     * Names an unaliased result column `grouping`.
     */
    public function outputName(): Name
    {
        return new Name('grouping');
    }

    /**
     * Derives the arguments; the result is an integer mask.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->arguments as $argument) {
            $derivation->scalar($argument, $environment);
        }

        return new ScalarFact(new Known(Builtin::Int4), Nullability::NotNull);
    }

    /**
     * Writes GROUPING and the arguments.
     */
    public function render(Output $out): void
    {
        $out->keyword('GROUPING')->symbol('(')->list($this->arguments)->symbol(')');
    }
}
