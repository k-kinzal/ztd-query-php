<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\JsonResults;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;

/**
 * Resolves the types of aggregate functions.
 *
 * COUNT and the bit aggregates are BIGINTs; MIN and MAX keep the type of their argument; SUM of
 * an exact number is a DECIMAL 22 digits wider, AVG one 4 digits and 4 decimals wider, and both
 * are doubles for a double, SUM(NULL) a DOUBLE 17 long without decimals and AVG(NULL) one 21
 * long with 4 (verified on live 8.0, 8.4 and 9.1 servers); the statistical aggregates are
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
     * Resolves an aggregate other than GROUP_CONCAT over an argument, or answers null for ST_COLLECT.
     */
    public function result(AggregateFunction $function, ?Domain $argument, GrammarRelease $release = GrammarRelease::MySql847): ?Domain
    {
        $numbers = new Numbers();
        $operand = $argument === null ? Kind::Integer : $numbers->operand($argument);
        [$precision, $scale] = $argument === null ? [1, 0] : $numbers->digits($argument);

        return match ($function) {
            AggregateFunction::Count => Domain::integer(Field::LongLong, 21),
            AggregateFunction::BitAnd, AggregateFunction::BitOr, AggregateFunction::BitXor => Domain::integer(Field::LongLong, 21, true),
            AggregateFunction::Minimum, AggregateFunction::Maximum => $argument?->kind === Kind::Json && $release !== GrammarRelease::MySql5744 ? JsonResults::json($release) : $argument ?? Domain::null(),
            AggregateFunction::Sum => match (true) {
                $argument?->kind === Kind::Null => Domain::double(17, 0),
                $operand === Kind::Double => Domain::double(23),
                default => Domain::decimal(min(65, $precision + 22), $scale),
            },
            AggregateFunction::Average => match (true) {
                $argument?->kind === Kind::Null => Domain::double(21, 4),
                $operand === Kind::Double => Domain::double(23),
                default => Domain::decimal(min(65, $precision + 4), min(30, $scale + 4)),
            },
            AggregateFunction::StandardDeviation, AggregateFunction::Variance, AggregateFunction::SampleStandardDeviation,
            AggregateFunction::SampleVariance => Domain::double(23),
            AggregateFunction::JsonArray => JsonResults::json($release, true),
            AggregateFunction::Collect => null,
        };
    }

    /**
     * Resolves GROUP_CONCAT over its arguments, or answers null after reporting collations that conflict.
     *
     * MySQL 5.6 uses a byte-limited result with zero decimals. Later releases use
     * character limits and report blob widths differently through 8.0 and from 8.4.
     * These metadata rules were observed through PDO with latin1, utf8mb3, utf8mb4,
     * UCS-2 and UTF-16; they do not change the byte limit applied to the value.
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
        $legacy = $release === GrammarRelease::MySql5651;
        $width = $collation->charset->maxLength;
        $characters = intdiv($this->settings->groupConcatMaxLen, $legacy ? $width : $collation->charset->minLength());
        if ($legacy) {
            return new Domain(Kind::String, $characters <= 512 ? Field::VarString : Field::Blob, $characters, 0, false, $collation, [], $coercibility);
        }
        $multiplier = in_array($release, [GrammarRelease::MySql5744, GrammarRelease::MySql8044], true) ? $width : $width * $width;

        return $characters <= 512 ? Domain::string($characters, $collation, Field::VarString, $coercibility) : Domain::string(min(4294967295, $characters * $multiplier), $collation, Field::LongBlob, $coercibility);
    }
}
