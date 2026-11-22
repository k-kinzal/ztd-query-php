<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
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
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A checked, nonempty list of operands evaluated without table inputs.
 *
 * @visibility public
 * @example A row containing a numbered parameter
 *     $row = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow([new \SqlSemantics\Platform\Sqlite\Ir\Parameter('02')]);
 *     $row->toString() // => '(?02)'
 */
final class ValueRow
{
    use Snapshot;

    /**
     * @var non-empty-list<Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression>
     */
    public readonly array $values;

    /**
     * Checks operand roles and copies the ordered input.
     *
     * @param array<array-key, mixed> $values
     */
    public function __construct(array $values)
    {
        Check::input(array_is_list($values) && $values !== [] && count($values) <= 2000, 'A VALUES row needs an ordered list of 1 to 2000 operands.');
        $checker = new ValueRowOperand();
        $closed = [];
        $pending = [];
        foreach ($values as $value) {
            Check::input(is_object($value), 'A VALUES row needs a scalar operand independent of tables.');
            $operand = $checker->checked($value);
            $closed[] = $operand;
            $pending[] = [$operand, 0, 0];
        }
        (new ExpressionScope())->check([], $pending);
        $this->values = $closed;
    }

    /**
     * Writes one parenthesized row of values in evaluation order.
     */
    public function toString(): string
    {
        $renderer = new SelectSql();
        $operands = [];
        foreach ($this->values as $value) {
            $operands[] = $renderer->expression($value, null);
        }

        return '(' . implode(', ', $operands) . ')';
    }
}
