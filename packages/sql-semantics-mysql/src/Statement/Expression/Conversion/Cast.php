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
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;

/**
 * A cast of a value to a type: `CAST(expr AS type [ARRAY])` or `CONVERT(expr, type)` (`Item_typecast_*`).
 *
 * CAST and typed CONVERT retain their written request forms; `CAST ... AT LOCAL` is refused while reading, as no release
 * executes it. `ARRAY` (MySQL 8.0.17 and later) casts a JSON array for a multi-valued index.
 *
 * Rule: MYSQL-CAST-001. Facts: MYSQL-CAST-RESULT-001; a cast to an array is
 * a JSON value. Unsupported element types and invalid target bounds
 * remain complete requests with ordered SQL conditions. Invalid conversions
 * have an Invalid type fact instead of an invented result domain. The operand takes a single value. Terminates: the operand
 * is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_cast,
 * https://dev.mysql.com/doc/refman/8.4/en/cast-functions.html#function_convert.
 * Status: Implemented.
 *
 * @visibility public
 * @example Preserving typed CONVERT
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WHERE CONVERT(a, SIGNED)')->toString() // => 'SELECT a FROM t WHERE CONVERT(a, SIGNED)'
 */
final class Cast implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar $operand The cast expression
     * @param CastTarget $target The target type
     * @param bool $array Whether ARRAY follows the target type
     */
    public function __construct(public readonly Scalar $operand, public readonly CastTarget $target, public readonly bool $array = false, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly CastForm $form = CastForm::Cast)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input($form !== CastForm::Convert || !$array, 'Typed CONVERT has no ARRAY clause.');
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
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices())->cast($this->target, $this->operand, $derivation, $this->array, $this->form);
        if ($problem !== null) {
            return new ScalarFact(new \SqlSemantics\Statement\Type\Invalid($problem), $fact->nullability);
        }
        $result = new CastResult();
        if ($this->array) {
            return new ScalarFact(new Known(new Elementary(ElementaryKind::Json)), $fact->nullability);
        }

        $operand = (new Precision())->domain($fact->type);
        $domain = $operand === null ? null : (new Casts(Settings::of($derivation->context), $grammar))->cast($operand, $this->target);
        if ($domain !== null) {
            (new \SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastNotices())->capacity($this->target, $domain, $derivation);
        }

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
        $out->keyword($this->form === CastForm::Convert ? 'CONVERT' : 'CAST')->glue()->symbol('(')->node($this->operand);
        if ($this->form === CastForm::Convert) {
            $out->symbol(',');
        } else {
            $out->keyword('AS');
        }
        $out->node($this->target);
        if ($this->array) {
            $out->keyword('ARRAY');
        }
        $out->symbol(')');
    }
}
