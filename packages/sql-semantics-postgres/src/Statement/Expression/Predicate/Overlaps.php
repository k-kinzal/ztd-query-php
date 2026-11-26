<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression\Predicate;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\OperandChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * A test of whether two time periods overlap: `(start1, end1) OVERLAPS (start2, end2)`.
 *
 * The parser reads it as a call of `pg_catalog.overlaps` with the four
 * fields; each period is a start and an end, or a start and a length.
 *
 * Rule: PG-OVERLAPS-001. Facts: `boolean`, NULL when a field can be (a row
 * constructor itself is never NULL, so its fields decide); a
 * period of other than two fields is reported, as the parser rejects it.
 * Source: https://www.postgresql.org/docs/17/functions-datetime.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Overlaps implements Scalar
{
    use Snapshot;

    /**
     * @param RowConstructor $left The first period
     * @param RowConstructor $right The second period
     */
    public function __construct(public readonly RowConstructor $left, public readonly RowConstructor $right)
    {
    }

    /**
     * Derives both periods and checks that each has two fields.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $facts = [$derivation->scalar($this->left, $environment), $derivation->scalar($this->right, $environment)];
        $nullability = (new OperandChecks())->fields($facts);
        foreach ([$this->left, $this->right] as $side => $row) {
            if (count($row->fields) !== 2) {
                $problem = new RowArity($side === 0 ? 'the left side of OVERLAPS' : 'the right side of OVERLAPS', 2, count($row->fields));
                $derivation->report($problem);

                return new ScalarFact(new Invalid($problem), $nullability);
            }
        }

        return new ScalarFact(new Known(Builtin::Bool), $nullability);
    }

    /**
     * Writes the two periods around OVERLAPS.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword('OVERLAPS')->node($this->right);
    }
}
