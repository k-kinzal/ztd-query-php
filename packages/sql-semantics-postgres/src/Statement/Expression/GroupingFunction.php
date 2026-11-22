<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

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
 * @visibility SqlSemantics
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
