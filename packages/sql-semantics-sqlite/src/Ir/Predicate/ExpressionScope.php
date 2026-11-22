<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
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
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks all expanded expression occurrences against their visible table slots.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 * @phpstan-type ScopedExpression array{Arithmetic|BlobLiteral|CaseExpression|CastExpression|ColumnReference|Comparison|HexIntegerLiteral|IntegerLiteral|Logical|NullLiteral|Parameter|RealLiteral|TextLiteral|UnaryExpression, int, int}
 */
final class ExpressionScope
{
    /**
     * Traverses the closed operand graph and applies the expression and SQL size budgets.
     *
     * @param list<int> $widths The number of slots per occurrence
     * @param list<ScopedExpression> $pending The expressions with height and scope width
     */
    public function check(array $widths, array $pending): void
    {
        $nodes = 0;
        $payloadBytes = 0;
        while ($pending !== []) {
            [$expression, $height, $visibility] = array_pop($pending);
            Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
            if ($expression instanceof CaseExpression) {
                foreach ($this->choice($expression, $height + 1, $visibility) as $operand) {
                    $pending[] = $operand;
                }
                continue;
            }
            match (true) {
                $expression instanceof ColumnReference => Check::input(
                    $expression->source < $visibility && $expression->slot < ($widths[$expression->source] ?? 0),
                    'Column slot must belong to the statement source at a visible occurrence.',
                ),
                $expression instanceof Parameter => $payloadBytes += strlen($expression->label),
                $expression instanceof CastExpression, $expression instanceof UnaryExpression => $pending[] = [$expression->operand, $height + 1, $visibility],
                $expression instanceof TextLiteral => $payloadBytes += strlen($expression->value) + substr_count($expression->value, "'"),
                $expression instanceof BlobLiteral => $payloadBytes += strlen($expression->hex),
                $expression instanceof RealLiteral => $payloadBytes += strlen($expression->whole) + strlen($expression->fraction ?? '') + strlen($expression->exponent ?? ''),
                $expression instanceof Arithmetic, $expression instanceof Comparison, $expression instanceof Logical
                    => array_push($pending, [$expression->right, $height + 1, $visibility], [$expression->left, $height + 1, $visibility]),
                $expression instanceof HexIntegerLiteral, $expression instanceof IntegerLiteral, $expression instanceof NullLiteral => null,
            };
            Check::input($payloadBytes <= 990000000, 'Scalar payloads exceed the SQLite statement size budget.');
        }
    }

    /**
     * Lists every conditional operand, including operands potentially skipped at evaluation.
     *
     * @return list<ScopedExpression>
     */
    public function choice(CaseExpression $expression, int $height, int $visibility): array
    {
        $operands = [];
        if ($expression->base !== null) {
            $operands[] = [$expression->base, $height, $visibility];
        }
        foreach ($expression->branches as $branch) {
            $operands[] = [$branch->when, $height, $visibility];
            $operands[] = [$branch->then, $height, $visibility];
        }
        if ($expression->otherwise !== null) {
            $operands[] = [$expression->otherwise, $height, $visibility];
        }

        return $operands;
    }
}
