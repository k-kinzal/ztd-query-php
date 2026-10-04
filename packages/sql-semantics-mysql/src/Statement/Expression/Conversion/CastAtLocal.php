<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A cast of a value at the local time zone: `CAST(expr AT LOCAL AS type [ARRAY])`, which the grammar accepts and the server rejects.
 *
 * Rule: MYSQL-CAST-AT-LOCAL-001. Facts: the server reports
 * `ER_NOT_SUPPORTED_YET` for AT LOCAL while parsing, so the request is
 * invalid (NotSupportedYet). Terminates: the operand is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast
 * (only AT TIME ZONE is documented).
 * Status: Implemented.
 *
 * @visibility public
 * @example Keeping the rejected form
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE CAST(a AT LOCAL AS DATE)')->toString() // => 'SELECT a FROM t WHERE CAST(a AT LOCAL AS DATE)'
 */
final class CastAtLocal implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $operand The cast expression
     * @param CastTarget $target The target type
     * @param bool $array Whether ARRAY follows the target type
     */
    public function __construct(public readonly Scalar $operand, public readonly CastTarget $target, public readonly bool $array = false)
    {
    }

    /**
     * Derives the operand and reports the unsupported form.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        $problem = new NotSupportedYet('AT LOCAL');
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), $fact->nullability);
    }

    /**
     * Writes CAST, the operand, AT LOCAL AS, the target and the optional ARRAY in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('CAST')->glue()->symbol('(')->node($this->operand)->keyword('AT', 'LOCAL', 'AS')->node($this->target);
        if ($this->array) {
            $out->keyword('ARRAY');
        }
        $out->symbol(')');
    }
}
