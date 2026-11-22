<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
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
     * @var non-empty-list<Arithmetic|BlobLiteral|CaseExpression|CastExpression|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|TruthTest|UnaryExpression>
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
        $closed = [];
        $pending = [];
        foreach ($values as $value) {
            Check::input($value instanceof Arithmetic || $value instanceof BlobLiteral || $value instanceof CaseExpression || $value instanceof CastExpression || $value instanceof Comparison
                || $value instanceof HexIntegerLiteral || $value instanceof IntegerLiteral || $value instanceof Logical
                || $value instanceof NullLiteral || $value instanceof Parameter || $value instanceof RealLiteral || $value instanceof TextLiteral
                || $value instanceof TruthTest || $value instanceof UnaryExpression, 'A VALUES row needs a scalar operand independent of tables.');
            $closed[] = $value;
            $pending[] = [$value, 0, 0];
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
