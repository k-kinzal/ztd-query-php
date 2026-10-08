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
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;
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
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;

/**
 * A cast of a value to a type: `CAST(expr AS type [ARRAY])` or `CONVERT(expr, type)` (`Item_typecast_*`).
 *
 * `CONVERT(expr, type)` is the same request as `CAST(expr AS type)` and is
 * written as CAST. `ARRAY` (MySQL 8.0.17 and later) casts a JSON array for
 * a multi-valued index.
 *
 * Rule: MYSQL-CAST-001. Facts: MYSQL-CAST-RESULT-001; a cast to an array is
 * a JSON value, or a problem for a type no multi-valued index takes
 * (arrayRefusal()). A TIME or DATETIME precision above 6 is a problem the
 * server finds while it parses the cast. The operand takes a single value. Terminates: the operand
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
        if ($this->target->kind === CastKind::NationalChar) {
            Deprecation::raise(Deprecated::National, $derivation);
        }
        if (($this->target->kind === CastKind::Time || $this->target->kind === CastKind::DateTime) && $this->target->length !== null && (int) $this->target->length > 6) {
            $problem = new TooBigPrecision((int) $this->target->length, 'CAST');
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));
        }
        if ($this->target->charset?->charset !== null) {
            Deprecation::charset($this->target->charset->charset->value, $derivation);
        }
        $result = new CastResult();
        if ($this->array) {
            $refusal = $this->arrayRefusal();
            if ($refusal === null) {
                return new ScalarFact(new Known(new Elementary(ElementaryKind::Json)), $fact->nullability);
            }
            $problem = new NotSupportedYet($refusal);
            $derivation->report($problem);
            $derivation->warn(new ParseFailure($problem, true));

            return new ScalarFact(new Invalid($problem), $fact->nullability);
        }

        $operand = (new Precision())->domain($fact->type);
        $domain = $operand === null ? null : (new Casts(Settings::of($derivation->context), $grammar))->cast($operand, $this->target);

        return new ScalarFact($domain === null ? $result->type($this->target) : new Known($domain), $result->nullability($this->target, $fact->nullability));
    }

    /**
     * The form the server refuses, when it resolves the expression, for a cast to an array outside a functional index.
     */
    public const ARRAY_OUTSIDE_INDEX = 'Use of CAST( .. AS .. ARRAY) outside of functional index in CREATE(non-SELECT)/ALTER TABLE or in general expressions';

    /**
     * Answers the form the server refuses while it parses a cast to an array, or null when the type is one a multi-valued index takes.
     *
     * A multi-valued index takes no character set, no BLOB, and none of JSON, the floating-point
     * types, YEAR and the spatial types; the parse stops there, in any statement. A cast to
     * another type is refused outside a functional index when the expression is resolved
     * (ARRAY_OUTSIDE_INDEX), which these facts do not tell (verified on a live 8.4 server).
     */
    public function arrayRefusal(): ?string
    {
        $kind = $this->target->kind;
        if ($this->target->charset !== null || $kind === CastKind::NationalChar) {
            return 'specifying charset for multi-valued index';
        }

        return match ($kind) {
            CastKind::Char, CastKind::Binary => $this->target->length === null ? 'CAST-ing data to array of char/binary BLOBs' : null,
            CastKind::Real => 'CAST-ing data to array of DOUBLE',
            CastKind::MultiLineString => 'CAST-ing data to array of MULTILINESTRING>',
            CastKind::Json, CastKind::Double, CastKind::Float, CastKind::Year, CastKind::Point, CastKind::LineString, CastKind::Polygon, CastKind::MultiPoint, CastKind::MultiPolygon, CastKind::GeometryCollection => 'CAST-ing data to array of ' . $kind->value,
            CastKind::Signed, CastKind::Unsigned, CastKind::Date, CastKind::Time, CastKind::DateTime, CastKind::Decimal => null,
        };
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
