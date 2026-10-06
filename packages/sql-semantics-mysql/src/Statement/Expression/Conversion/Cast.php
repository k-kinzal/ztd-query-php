<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\CastResult;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * A cast of a value to a type: `CAST(expr AS type [ARRAY])` or `CONVERT(expr, type)` (`Item_typecast_*`).
 *
 * `CONVERT(expr, type)` is the same request as `CAST(expr AS type)` and is
 * written as CAST. `ARRAY` (MySQL 8.0.17 and later) casts a JSON array for
 * a multi-valued index.
 *
 * Rule: MYSQL-CAST-001. Facts: MYSQL-CAST-RESULT-001; a cast to an array is
 * a JSON value. The operand takes a single value. Terminates: the operand
 * is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast,
 * https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_convert.
 * Status: Implemented.
 *
 * @visibility public
 * @example Writing CONVERT with a type as CAST
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE CONVERT(a, SIGNED)')->toString() // => 'SELECT a FROM t WHERE CAST(a AS SIGNED)'
 */
final class Cast implements Scalar
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
     * Derives the operand and the type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $grammar = $derivation->context->profile->grammar;
        Check::input(!$this->array || ($grammar !== GrammarRelease::MySql5651 && $grammar !== GrammarRelease::MySql5744), 'A cast to an array needs MySQL 8.0 or later.');
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        $result = new CastResult();
        if ($this->array) {
            return new ScalarFact(new Known(new Elementary(ElementaryKind::Json)), $fact->nullability);
        }

        return new ScalarFact($result->type($this->target), $result->nullability($this->target, $fact->nullability));
    }

    /**
     * Writes CAST, the operand, AS, the target and the optional ARRAY in parentheses.
     */
    public function render(Output $out): void
    {
        $out->keyword('CAST')->glue()->symbol('(')->node($this->operand)->keyword('AS')->node($this->target);
        if ($this->array) {
            $out->keyword('ARRAY');
        }
        $out->symbol(')');
    }
}
