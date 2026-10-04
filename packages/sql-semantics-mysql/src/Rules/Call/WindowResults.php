<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Expression\TypeAggregation;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge;
use SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * The result of a function that is only a window function.
 *
 * Rule: MYSQL-WINDOW-RESULT-001. ROW_NUMBER, RANK, DENSE_RANK and NTILE are
 * BIGINT UNSIGNED, CUME_DIST and PERCENT_RANK DOUBLE; none of them is NULL.
 * LEAD and LAG have the aggregated type of the value and the default
 * (MYSQL-TYPE-AGGREGATION-001); FIRST_VALUE, LAST_VALUE and NTH_VALUE
 * the type of the value; each is NULL when the row is outside the frame or
 * partition. IGNORE NULLS and FROM LAST are rejected by the server
 * (ER_NOT_SUPPORTED_YET). Terminates: a fixed number of tests on one call.
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
            WindowFunctionKind::Tile => new ScalarFact(new Known(TypeClass::Unsigned->descriptor()), Nullability::NotNull),
            WindowFunctionKind::CumulativeDistribution,
            WindowFunctionKind::PercentRank => new ScalarFact(new Known(TypeClass::Floating->descriptor()), Nullability::NotNull),
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => new ScalarFact(
                (new TypeAggregation())->aggregate([$arguments[0]->type, ...(isset($arguments[2]) ? [$arguments[2]->type] : [])]),
                Nullability::Nullable,
            ),
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue,
            WindowFunctionKind::NthValue => new ScalarFact($arguments[0]->type ?? new NullOnly(), Nullability::Nullable),
        };
    }
}
