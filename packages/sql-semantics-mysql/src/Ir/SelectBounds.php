<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Ir\Binding\Parameter;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\MySql\Ir\Expression\SignedCast;
use SqlSemantics\Platform\MySql\Ir\Expression\UnsignedCast;
use SqlSemantics\Platform\MySql\Ir\From\DerivedSource;
use SqlSemantics\Platform\MySql\Ir\From\Join;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks scope membership and expanded expression limits at the SELECT boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SelectBounds
{
    /**
     * Checks every expression occurrence, including repeated uses of a shared value.
     *
     * @param non-empty-list<ResultColumn> $results The result expressions
     * @param TableSource|Join|null $source The source whose slots may be used
     */
    public function check(
        array $results,
        TableSource|Join|null $source,
        BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|TruthTest|null $where = null,
        bool $ordinaryProjection = false,
    ): void {
        $wide = count($results) > 1024;
        Check::input(
            !$wide || ($ordinaryProjection && $source === null && $where === null && count($results) <= 4097),
            'A SELECT with more than 1024 results must be an independent projection of at most 4097 signed literals.'
        );
        $inputs = $source instanceof Join ? $source->inputs() : ($source === null ? [] : [$source]);
        $scope = new OccurrenceScope();
        $scope->predicate($where, $inputs);
        $nodes = 0;
        $position = 0;
        foreach ($results as $result) {
            Check::input(
                !$wide || $result->expression instanceof IntegerLiteral || $result->expression instanceof ProjectedIntegerLiteral,
                'A SELECT with more than 1024 results must be an independent projection of at most 4097 signed literals.'
            );
            [$nodes, $position] = $this->checkResult($result, $inputs, $scope, $nodes, $position);
        }
    }

    /**
     * Checks one result and returns the updated expanded bounds and marker position.
     * @param list<TableSource|DerivedSource> $inputs
     * @return array{int,int}
     */
    public function checkResult(ResultColumn $result, array $inputs, OccurrenceScope $scope, int $nodes, int $position): array
    {
        $this->checkLabel($result, $inputs);
        $pending = [[$result->expression, 0]];
        while ($pending !== []) {
            [$expression, $height] = array_pop($pending);
            if ($expression instanceof SignedCase || $expression instanceof IntegerComparison || $expression instanceof IntegerMembership || $expression instanceof IntegerRange || $expression instanceof NullTest || $expression instanceof TruthTest || $expression instanceof TextComparison) {
                $invalid = $expression instanceof IntegerRange ? 'A range slot must belong to the SELECT source.'
                    : 'A predicate slot must belong to the SELECT source.';
                $nodes = $scope->checkTree($expression, $inputs, $invalid, $height, $nodes);
                continue;
            }
            Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
            match (true) {
                $expression instanceof ColumnReference => Check::input(
                    $scope->hasSlot($inputs[$expression->occurrence] ?? null, $expression->slot),
                    'Column slot must belong to the SELECT source.',
                ),
                $expression instanceof TextColumnReference => $scope->textColumn($expression, $inputs),
                $expression instanceof SignedCast, $expression instanceof UnsignedCast => $pending[] = [$expression->operand, $height + 1],
                $expression instanceof CollatedText => $pending[] = [$expression->operand, $height + 1],
                $expression instanceof Arithmetic => array_push($pending, [$expression->right, $height + 1], [$expression->left, $height + 1]),
                $expression instanceof Parameter => Check::input($expression->position === ++$position, 'MySQL parameter positions must follow lexical projection order.'),
                $expression instanceof IntegerLiteral, $expression instanceof RangeIntegerLiteral, $expression instanceof ProjectedIntegerLiteral, $expression instanceof BooleanLiteral, $expression instanceof NullLiteral, $expression instanceof TextLiteral => null,
            };
        }

        return [$nodes, $position];
    }

    /**
     * Checks a result name that relies on the physical column's own label.
     * @param list<TableSource|DerivedSource> $inputs
     */
    public function checkLabel(ResultColumn $result, array $inputs): void
    {
        $label = $result->name->value;
        if ($label !== ltrim($label, "\x00..\x20\x7f")) {
            $reference = $result->expression;
            Check::invariant($reference instanceof ColumnReference || $reference instanceof TextColumnReference, 'An intrinsic label belongs to a column reference.');
            $table = $inputs[$reference->occurrence] ?? null;
            Check::input(
                $table instanceof TableSource && ($table->columns[$reference->slot]->value ?? null) === $label,
                'An intrinsic MySQL result name must equal its physical input field.'
            );
        }
    }
}
