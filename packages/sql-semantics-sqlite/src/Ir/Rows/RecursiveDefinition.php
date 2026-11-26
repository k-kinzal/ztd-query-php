<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A recursive definition with one anchor and one self-fed SELECT arm.
 *
 * @visibility public
 * @phpstan-import-type ScopedExpression from ExpressionScope
 * @example Reading the duplicate admission policy
 *     $query = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('WITH RECURSIVE c(n) AS (VALUES (1) UNION ALL SELECT n+1 FROM c WHERE n<3) SELECT n FROM c');
 *     $query->source->query->unionAll // => true
 */
final class RecursiveDefinition
{
    use Snapshot;

    /**
     * @var non-empty-list<RecursiveColumn>
     */
    public readonly array $step;

    /**
     * Checks the successor width, reference scope and the combined operand budget.
     * @param list<RecursiveColumn> $step The ordered successor outputs
     */
    public function __construct(
        public readonly ValuesQuery $anchor,
        array $step,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null $where = null,
        public readonly bool $unionAll = false,
    ) {
        $outputs = Check::listOf($step, RecursiveColumn::class, 'The recursive step needs checked output columns.', 1);
        $width = count($anchor->rows[0]->values);
        Check::input(count($outputs) === $width, 'The recursive step must match the anchor width.');
        $this->step = $outputs;
        (new ExpressionScope())->check([$width], $this->scopedExpressions());
    }

    /**
     * Supplies the anchor and successor operands in their distinct lexical scopes.
     * @return non-empty-list<ScopedExpression>
     * @visibility SqlSemantics\Platform\Sqlite
     */
    public function scopedExpressions(): array
    {
        $pending = [];
        foreach ($this->step as $column) {
            $pending[] = [$column->expression, 0, 1];
        }
        if ($this->where !== null) {
            $pending[] = [$this->where, 0, 1];
        }
        foreach ($this->anchor->rows as $row) {
            foreach ($row->values as $value) {
                $pending[] = [$value, 0, 0];
            }
        }

        return $pending;
    }
}
