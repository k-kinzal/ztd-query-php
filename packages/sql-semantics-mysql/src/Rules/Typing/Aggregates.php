<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
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
 * are doubles for a double; the statistical aggregates are doubles; JSON_ARRAYAGG is JSON.
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
    public function result(AggregateFunction $function, ?Domain $argument): ?Domain
    {
        $numbers = new Numbers();
        $operand = $argument === null ? Kind::Integer : $numbers->operand($argument);
        [$precision, $scale] = $argument === null ? [1, 0] : $numbers->digits($argument);

        return match ($function) {
            AggregateFunction::Count => Domain::integer(Field::LongLong, 21),
            AggregateFunction::BitAnd, AggregateFunction::BitOr, AggregateFunction::BitXor => Domain::integer(Field::LongLong, 21, true),
            AggregateFunction::Minimum, AggregateFunction::Maximum => $argument ?? Domain::null(),
            AggregateFunction::Sum => $operand === Kind::Double ? Domain::double(23) : Domain::decimal(min(65, $precision + 22), $scale),
            AggregateFunction::Average => $operand === Kind::Double ? Domain::double(23) : Domain::decimal(min(65, $precision + 4), min(30, $scale + 4)),
            AggregateFunction::StandardDeviation, AggregateFunction::Variance, AggregateFunction::SampleStandardDeviation,
            AggregateFunction::SampleVariance => Domain::double(23),
            AggregateFunction::JsonArray => new Domain(Kind::Json, Field::Json, 4294967295, Domain::NOT_FIXED, false, Collation::known('utf8mb4_bin')),
            AggregateFunction::Collect => null,
        };
    }

    /**
     * Resolves GROUP_CONCAT over its arguments, or answers null after reporting collations that conflict.
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
        $limit = $this->settings->groupConcatMaxLen;

        return $limit <= 512 ? Domain::string($limit, $collation, Field::VarString, $coercibility) : Domain::string(min(4294967295, $limit * 16), $collation, Field::LongBlob, $coercibility);
    }
}
