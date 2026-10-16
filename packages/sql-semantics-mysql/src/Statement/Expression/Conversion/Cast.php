<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Conversion;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\CastResult;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Rules\Typing\Casts;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
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
 * a JSON value. Construction excludes element types no multi-valued index
 * takes. A TIME or DATETIME precision above 6 is a problem the
 * server finds while it parses the cast; construction excludes it. The operand takes a single value. Terminates: the operand
 * is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast,
 * https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_convert.
 * Status: Implemented.
 *
 * @visibility public
 * @example Writing CONVERT with a type as CAST
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE CONVERT(a, SIGNED)')->toString() // => 'SELECT a FROM t WHERE CAST(a AS SIGNED)'
 * @example Refusing an impossible temporal precision
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Time, '7')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an impossible floating precision
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Float, '54')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an impossible array element type
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Json), true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
        Check::input(!$array || (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems())->array($target) === null, 'An array cast needs a supported multi-valued index element type.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems())->precision($target) === null, 'A CAST precision must fit its target type.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the operand and the type of the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $grammar = $derivation->context->profile->grammar;
        Check::input(!$this->array || ($grammar !== GrammarRelease::MySql5651 && $grammar !== GrammarRelease::MySql5744), 'A cast to an array needs MySQL 8.0 or later.');
        $fact = (new Operands())->single($derivation->scalar($this->operand, $environment), $derivation);
        if ($this->target->kind === CastKind::NationalChar) {
            Deprecation::raise(Deprecated::National, $derivation);
        }
        if ($this->target->charset?->charset !== null) {
            Deprecation::charset($this->target->charset->charset->value, $derivation);
            if (\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($this->target->charset->charset->value) === null) {
                $problem = new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset($this->target->charset->charset->value);
                $derivation->report($problem);
                $derivation->warn(new ParseFailure($problem, true));
            }
        }
        $result = new CastResult();
        if ($this->array) {
            return new ScalarFact(new Known(new Elementary(ElementaryKind::Json)), $fact->nullability);
        }

        $operand = (new Precision())->domain($fact->type);
        $domain = $operand === null ? null : (new Casts(Settings::of($derivation->context), $grammar))->cast($operand, $this->target);

        return new ScalarFact($domain === null ? $result->type($this->target) : new Known($domain), $result->nullability($this->target, $fact->nullability, $grammar));
    }

    /**
     * The form the server refuses, when it resolves the expression, for a cast to an array outside a functional index.
     */
    public const ARRAY_OUTSIDE_INDEX = 'Use of CAST( .. AS .. ARRAY) outside of functional index in CREATE(non-SELECT)/ALTER TABLE or in general expressions';

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
