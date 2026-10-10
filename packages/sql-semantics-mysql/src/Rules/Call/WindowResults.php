<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * The result of a function that is only a window function.
 *
 * Rule: MYSQL-WINDOW-RESULT-001. ROW_NUMBER, RANK, DENSE_RANK and NTILE are
 * BIGINT, CUME_DIST and PERCENT_RANK DOUBLE; none of them is NULL.
 * LEAD and LAG have the aggregated type of the value and the default
 * (MYSQL-TYPE-AGGREGATION-001), JSON for a JSON value and a JSON or NULL
 * default; FIRST_VALUE, LAST_VALUE and NTH_VALUE
 * the type of the value; each is read from the temporary table of the
 * window, which holds an integer as an INT or a BIGINT and a string as a
 * VARCHAR or a BLOB. FIRST_VALUE, LAST_VALUE and NTH_VALUE are NULL when
 * the frame has no such row, LEAD and LAG when the row is outside the
 * partition and the default is NULL or can be. IGNORE NULLS and FROM LAST
 * are rejected by the server (ER_NOT_SUPPORTED_YET). Terminates: a fixed
 * number of tests on one call (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WindowResults
{
    /**
     * Answers the facts of a window function call and reports what the server rejects.
     *
     * @param list<ScalarFact> $arguments
     */
    public function result(WindowFunction $call, array $arguments, Derivation $derivation): ScalarFact
    {
        if ($call->nulls === NullTreatment::Ignore) {
            $derivation->report(new UnsupportedWindowing(WindowingLimit::IgnoreNulls));
        }
        if ($call->edge === CountingEdge::Last) {
            $derivation->report(new UnsupportedWindowing(WindowingLimit::FromLast));
        }

        return match ($call->kind) {
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank,
            WindowFunctionKind::Tile => new ScalarFact(new Known(TypeClass::Integer->descriptor()), Nullability::NotNull),
            WindowFunctionKind::CumulativeDistribution,
            WindowFunctionKind::PercentRank => new ScalarFact(new Known(TypeClass::Floating->descriptor()), Nullability::NotNull),
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => new ScalarFact(
                (new TypeAggregation())->aggregate([$arguments[0]->type, ...(isset($arguments[2]) ? [$arguments[2]->type] : [])]),
                (isset($arguments[2]) && $arguments[0]->nullability === Nullability::NotNull && $arguments[2]->nullability === Nullability::NotNull ? Nullability::NotNull : Nullability::Nullable),
            ),
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue,
            WindowFunctionKind::NthValue => new ScalarFact($arguments[0]->type ?? new NullOnly(), Nullability::Nullable),
        };
    }

    /**
     * Resolves the type of a window function call over the resolved types of its arguments, or answers null after reporting collations that conflict.
     *
     * @param list<Domain> $arguments
     */
    public function resolved(WindowFunction $call, array $arguments, Derivation $derivation): ?Domain
    {
        return match ($call->kind) {
            WindowFunctionKind::RowNumber, WindowFunctionKind::Rank, WindowFunctionKind::DenseRank, WindowFunctionKind::Tile => Domain::integer(Field::LongLong, 21),
            WindowFunctionKind::CumulativeDistribution, WindowFunctionKind::PercentRank => Domain::double(23),
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue, WindowFunctionKind::NthValue => (new Materialization())->windowed($arguments[0], $derivation->context->profile->grammar),
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => $this->shifted($call, $arguments, $derivation),
        };
    }

    /**
     * Resolves the type of LEAD or LAG: the value and the default aggregated, JSON when both are JSON or the default is NULL, as the temporary table of the window holds it.
     *
     * MySQL 8.0 reports such a JSON value in the collation of JSON text (verified on a live 8.0 server).
     *
     * @param list<Domain> $arguments
     */
    public function shifted(WindowFunction $call, array $arguments, Derivation $derivation): ?Domain
    {
        $values = [$arguments[0], ...(isset($arguments[2]) ? [$arguments[2]] : [])];
        $json = $arguments[0]->kind === Kind::Json && array_filter($values, static fn (Domain $value): bool => $value->kind !== Kind::Json && $value->kind !== Kind::Null) === [];
        $settings = Settings::of($derivation->context);
        $release = $derivation->context->profile->grammar;
        if ($json && $release === GrammarRelease::MySql8044) {
            return new Domain(Kind::Json, Field::Json, 4294967295, 0, false, Collation::known('utf8mb4_bin'));
        }
        $aggregated = $json ? $arguments[0] : (new Aggregation(new Collations($settings->connection)))->of($values, strtolower($call->kind->value), $derivation);

        return $aggregated === null ? null : (new Materialization())->windowed($aggregated, $release);
    }
}
