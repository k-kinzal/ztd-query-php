<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\JsonResults;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the types of aggregate functions.
 *
 * COUNT and the bit aggregates are BIGINTs; MIN and MAX keep the type of their argument; SUM of
 * an exact number is a DECIMAL 22 digits wider; AVG adds div_precision_increment
 * to its precision and scale, with at most 30 fractional digits. Both are doubles
 * for a double. SUM(NULL) is a DOUBLE 17 long without decimals; AVG(NULL) adds the
 * increment to a base width of zero in 5.6/5.7 and 17 in later releases. Legacy
 * floating averages widen their operand's byte width, while later releases use
 * 23 for unfixed results and 17 plus the scale for fixed results. Fixed floating
 * scales also gain the increment. Only 9.1 caps exact precision at 65. Numeric
 * aggregates in 5.6/5.7 retain coercibility 4 and the binary collation. These rules were observed
 * on 5.6, 5.7, 8.0, 8.4 and 9.1 through the native protocol. The statistical aggregates are
 * doubles; JSON_ARRAYAGG is JSON.
 * GROUP_CONCAT is a string in the collation its arguments aggregate to, as long as
 * group_concat_max_len up to 512 characters and a long blob beyond.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Aggregates
{
    /**
     * @param Settings $settings The session the aggregates are resolved in
     */
    public function __construct(public readonly Settings $settings)
    {
    }

    /**
     * Resolves an aggregate other than GROUP_CONCAT over an argument.
     */
    public function result(AggregateFunction $function, ?Domain $argument, GrammarRelease $release = GrammarRelease::MySql847, bool $column = false): Domain
    {
        $numbers = new Numbers();
        $operand = $argument === null ? Kind::Integer : $numbers->operand($argument);
        [$precision, $scale] = $argument === null ? [1, 0] : $numbers->digits($argument);
        $increment = $this->settings->divPrecisionIncrement;
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);

        $result = match ($function) {
            AggregateFunction::Count => Domain::integer(Field::LongLong, 21),
            AggregateFunction::BitAnd, AggregateFunction::BitOr, AggregateFunction::BitXor => Domain::integer(Field::LongLong, 21, true),
            AggregateFunction::Minimum, AggregateFunction::Maximum => $this->extreme($argument, $release, $column),
            AggregateFunction::Sum => match (true) {
                $argument?->kind === Kind::Null => Domain::double(17, 0),
                $operand === Kind::Double => Domain::double(23),
                default => Domain::decimal($this->exactPrecision($precision + 22, $release), $scale),
            },
            AggregateFunction::Average => match (true) {
                $argument?->kind === Kind::Null => Domain::double(($legacy ? 0 : 17) + $increment, $increment),
                $operand === Kind::Double => $this->floatingAverage($argument, $legacy),
                default => Domain::decimal($this->exactPrecision($precision + $increment, $release), min(30, $scale + $increment)),
            },
            AggregateFunction::StandardDeviation, AggregateFunction::Variance, AggregateFunction::SampleStandardDeviation,
            AggregateFunction::SampleVariance => Domain::double(23),
            AggregateFunction::JsonArray => JsonResults::json($release, true),
            AggregateFunction::Collect => new Domain(Kind::String, Field::Geometry, 16777216, Domain::NOT_FIXED, false, Collation::binary(), [], Coercibility::Coercible),
        };

        return $legacy && in_array($function, [AggregateFunction::Sum, AggregateFunction::Average, AggregateFunction::Count, AggregateFunction::StandardDeviation, AggregateFunction::Variance, AggregateFunction::SampleStandardDeviation, AggregateFunction::SampleVariance], true) ? $result->withCollation(Collation::binary(), Coercibility::Coercible) : $result;
    }

    /**
     * Caps widened exact aggregate precision only in MySQL 9.1.
     * Earlier releases expose the full bound in result metadata, even above 65.
     */
    public function exactPrecision(int $precision, GrammarRelease $release): int
    {
        return $release === GrammarRelease::MySql910 ? min(65, $precision) : $precision;
    }

    /**
     * Types floating averages. Only a floating operand has a fixed numeric scale;
     * text columns' zero display decimals do not fix their numeric conversion.
     * A scale beyond 30 becomes unfixed. Observed with empty and populated tables
     * on 5.6, 5.7, 8.0, 8.4 and 9.1 at precision increments 0, 4, 8 and 30.
     */
    public function floatingAverage(Domain $argument, bool $legacy): Domain
    {
        $increment = $this->settings->divPrecisionIncrement;
        $scale = $argument->kind === Kind::Double ? min(Domain::NOT_FIXED, $argument->decimals + $increment) : Domain::NOT_FIXED;

        return Domain::double($legacy ? $argument->metadataLength() + $increment : ($scale === Domain::NOT_FIXED ? 23 : 17 + $scale), $scale);
    }

    /**
     * Resolves MIN and MAX. A TEXT/BLOB column keeps its expression bound while
     * reporting its collation-expanded byte width, without fixed decimals. This
     * preserves the bound used by CONCAT and HEX over the aggregate. Legacy
     * computed strings choose their result field by their expression width.
     * Computed blob expressions retain their separate metadata. Verified through SQL on
     * MySQL 5.6, 5.7 and 8.4, including scalar subqueries and set operands.
     */
    public function extreme(?Domain $argument, GrammarRelease $release, bool $column): Domain
    {
        if ($argument?->kind === Kind::Json && $release !== GrammarRelease::MySql5744) {
            return JsonResults::json($release);
        }
        if ($argument?->kind === Kind::Bit && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return new Domain(Kind::Integer, Field::Bit, $argument->length, 0, true, $argument->collation, [], $argument->coercibility);
        }
        if ($argument?->kind === Kind::String) {
            return (new Text\ResultBounds())->selected($argument, !$column && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true), $column);
        }
        if ($argument?->kind === Kind::Double && in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return new Domain(Kind::Double, $argument->field, $argument->decimals === Domain::NOT_FIXED ? 23 : 17 + $argument->decimals, $argument->decimals, $argument->unsigned, $argument->collation, [], $argument->coercibility);
        }

        return $argument ?? Domain::null();
    }

    /**
     * Resolves GROUP_CONCAT over its arguments, or answers null after reporting collations that conflict.
     *
     * MySQL 5.6 and 5.7 use a byte-limited result with zero decimals. Later releases use
     * character limits and report blob widths differently through 8.0 and from 8.4.
     * These metadata rules were observed through PDO with latin1, utf8mb3, utf8mb4,
     * UCS-2 and UTF-16; they do not change the byte limit applied to the value.
     * Blob results keep their expression character bound separately from their
     * explicit metadata width, so CONCAT and HEX do not expand that width twice.
     *
     * @param list<Domain> $arguments
     */
    public function concatenated(array $arguments, Derivation $derivation): ?Domain
    {
        $settled = (new Collations($this->settings->connection))->aggregate($arguments, 'group_concat', $derivation);
        if ($settled === null) {
            return null;
        }
        [$collation, $coercibility] = $settled;
        $release = $derivation->context->profile->grammar;
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $width = $collation->charset->maxLength;
        $characters = intdiv($this->settings->groupConcatMaxLen, $legacy ? $width : $collation->charset->minLength());
        if ($legacy) {
            return new Domain(Kind::String, $characters <= 512 ? Field::VarString : Field::Blob, $characters, 0, false, $collation, [], $coercibility, $characters > 512 || $this->settings->groupConcatMaxLen % $width !== 0 ? $this->settings->groupConcatMaxLen : null);
        }
        $multiplier = $release === GrammarRelease::MySql8044 ? $width : $width * $width;

        return $characters <= 512 ? Domain::string($characters, $collation, Field::VarString, $coercibility) : new Domain(Kind::String, Field::LongBlob, min(4294967295, $characters * intdiv($multiplier, $width)), Domain::NOT_FIXED, false, $collation, [], $coercibility, min(4294967295, $characters * $multiplier));
    }
}
