<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Predicate;

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
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks all expanded expression occurrences against their visible table slots.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 * @phpstan-type ScopedExpression array{Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression, int, int, 3?: true}
 */
final class ExpressionScope
{
    /**
     * Traverses the closed operand graph and applies the expression and SQL size budgets.
     *
     * @param list<int> $widths The number of slots per occurrence
     * @param list<ScopedExpression> $pending The expressions with height and scope width
     * @param bool $allowLiveParameters Whether the owner accepts parameters that are evaluated
     * @param bool $allowRegistrations Whether the owner accepts inert parameter registrations
     * @return list<ColumnReference> The checked occurrences in the expanded operand graph
     */
    public function check(array $widths, array $pending, bool $allowLiveParameters = true, bool $allowRegistrations = true): array
    {
        $references = [];
        $nodes = 0;
        $payloadBytes = 0;
        while ($pending !== []) {
            $frame = array_pop($pending);
            [$expression, $height, $visibility] = $frame;
            Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
            if ($expression instanceof CaseExpression || $expression instanceof MembershipTest || $expression instanceof FoldedMembership || $expression instanceof PatternTest || $expression instanceof Coalesce) {
                foreach ($this->branches($expression, $height + 1, $visibility) as $operand) {
                    $pending[] = $operand;
                }
                continue;
            }
            match (true) {
                $expression instanceof ColumnReference => $references[] = $this->checkReference($expression, $widths, $visibility),
                $expression instanceof Parameter => $payloadBytes += $this->parameterBytes($expression, $allowLiveParameters, isset($frame[3]), $allowRegistrations),
                $expression instanceof CastExpression, $expression instanceof CollatedExpression, $expression instanceof TruthTest, $expression instanceof UnaryExpression
                    => $pending[] = [$expression->operand, $height + 1, $visibility],
                $expression instanceof BetweenTest => array_push(
                    $pending,
                    [$expression->upper, $height + 1, $visibility],
                    [$expression->lower, $height + 1, $visibility],
                    [$expression->operand, $height + 1, $visibility],
                ),
                $expression instanceof TextLiteral => $payloadBytes += $expression->payloadBytes(),
                $expression instanceof BlobLiteral => $payloadBytes += strlen($expression->hex),
                $expression instanceof RealLiteral => $payloadBytes += $expression->payloadBytes(),
                $expression instanceof Arithmetic, $expression instanceof Comparison, $expression instanceof Concatenation, $expression instanceof Logical
                    => array_push($pending, [$expression->right, $height + 1, $visibility], [$expression->left, $height + 1, $visibility]),
                $expression instanceof HexIntegerLiteral, $expression instanceof IntegerLiteral, $expression instanceof NullLiteral => null,
            };
            if ($expression instanceof CastExpression && $expression->typeName !== null) {
                $payloadBytes += $expression->typeName->payloadBytes();
            }
            Check::input($payloadBytes <= 990000000, 'Scalar payloads exceed the SQLite statement size budget.');
        }

        return $references;
    }

    /**
     * Checks the marker's role and counts its SQL payload.
     */
    public function parameterBytes(Parameter $parameter, bool $allowLiveParameters, bool $registration, bool $allowRegistrations = true): int
    {
        Check::input($allowLiveParameters || ($allowRegistrations && $registration), 'A schema expression cannot contain a parameter.');

        return strlen($parameter->label);
    }

    /**
     * Checks one slot against the visible input prefix.
     * @param list<int> $widths The widths of the declared input occurrences
     */
    public function checkReference(ColumnReference $expression, array $widths, int $visibility): ColumnReference
    {
        Check::input(
            $expression->source < $visibility && $expression->slot < ($widths[$expression->source] ?? 0),
            'Column slot must belong to the statement source at a visible occurrence.',
        );

        return $expression;
    }

    /**
     * Enumerates typed children for variable-arity scalar roles.
     * @return list<ScopedExpression>
     */
    public function branches(CaseExpression|Coalesce|FoldedMembership|MembershipTest|PatternTest $expression, int $height, int $visibility): array
    {
        if ($expression instanceof CaseExpression) {
            return $this->choice($expression, $height, $visibility);
        }
        if ($expression instanceof Coalesce) {
            return $this->coalesce($expression, $height, $visibility);
        }
        if ($expression instanceof MembershipTest) {
            return $this->members($expression, $height, $visibility);
        }
        if ($expression instanceof PatternTest) {
            return $this->pattern($expression, $height, $visibility);
        }

        $operands = [];
        foreach ($expression->bindings as $binding) {
            $operands[] = [$binding, $height, $visibility, true];
        }

        return $operands;
    }

    /**
     * Visits each ordered conditional argument in the enclosing scope.
     * @return non-empty-list<ScopedExpression>
     */
    public function coalesce(Coalesce $expression, int $height, int $visibility): array
    {
        $operands = [];
        foreach ($expression->arguments as $argument) {
            $operands[] = [$argument->expression, $height, $visibility];
        }

        return $operands;
    }

    /**
     * Visits all pattern arguments in lexical order.
     * @return non-empty-list<ScopedExpression>
     */
    public function pattern(PatternTest $expression, int $height, int $visibility): array
    {
        $operands = [[$expression->subject, $height, $visibility], [$expression->pattern, $height, $visibility]];
        if ($expression->escape !== null) {
            $operands[] = [$expression->escape, $height, $visibility];
        }

        return $operands;
    }

    /**
     * Lists the tested value followed by every ordered membership candidate.
     * @return list<ScopedExpression>
     */
    public function members(MembershipTest $expression, int $height, int $visibility): array
    {
        $operands = [[$expression->operand, $height, $visibility]];
        foreach ($expression->items as $item) {
            $operands[] = [$item->expression, $height, $visibility];
        }

        return $operands;
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
