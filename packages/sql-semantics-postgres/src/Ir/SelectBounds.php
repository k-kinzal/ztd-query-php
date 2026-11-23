<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Binding\Parameter;
use SqlSemantics\Platform\PostgreSql\Ir\Binding\ParameterSignature;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\BuiltinCast;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\CollatedText;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\Join;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks scope membership and expanded expression limits at the SELECT boundary.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
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
        BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TruthTest|null $where = null,
    ): void {
        Check::input(count($results) <= 1024, 'A SELECT can have at most 1024 result columns in this profile.');
        $inputs = $source instanceof Join ? $source->inputs() : ($source === null ? [] : [$source]);
        $scope = new OccurrenceScope();
        $scope->predicate($where, $inputs);
        $nodes = 0;
        $positions = [];
        foreach ($results as $result) {
            $pending = [[$result->expression, 0]];
            while ($pending !== []) {
                [$expression, $height] = array_pop($pending);
                if ($expression instanceof IntegerRange || $expression instanceof IntegerDistinctness || $expression instanceof NullTest || $expression instanceof TruthTest) {
                    $nodes = $scope->checkTree($expression, $inputs, 'A tested predicate slot must belong to the SELECT source.', $height, $nodes);
                    continue;
                }
                Check::input($height <= 32 && ++$nodes <= 8192, 'Expression height or expanded node count exceeds the IR profile.');
                if ($expression instanceof IntegerCase) {
                    foreach ($expression->arms as $arm) {
                        $nodes = $scope->checkTree($arm->condition, $inputs, 'A CASE condition slot must belong to the SELECT source.', $height + 1, $nodes);
                        $pending[] = [$arm->result, $height + 1];
                    }
                    if ($expression->otherwise !== null) {
                        $pending[] = [$expression->otherwise, $height + 1];
                    }
                    continue;
                }
                match (true) {
                    $expression instanceof ColumnReference, $expression instanceof TextColumnReference => $scope->column($expression, $inputs),
                    $expression instanceof BuiltinCast, $expression instanceof CollatedText => $pending[] = [$expression->operand, $height + 1],
                    $expression instanceof Arithmetic => array_push($pending, [$expression->right, $height + 1], [$expression->left, $height + 1]),
                    $expression instanceof Parameter => $positions[$expression->position] = ParameterSignature::typeFor($positions[$expression->position] ?? null, $expression),
                    $expression instanceof IntegerLiteral, $expression instanceof WideIntegerLiteral, $expression instanceof BooleanLiteral, $expression instanceof NullLiteral, $expression instanceof TextLiteral => null,
                };
            }
        }
        for ($position = 1; $position <= count($positions); ++$position) {
            Check::input(isset($positions[$position]), 'PostgreSQL parameter positions must cover the contiguous bound slots.');
        }
    }
}
