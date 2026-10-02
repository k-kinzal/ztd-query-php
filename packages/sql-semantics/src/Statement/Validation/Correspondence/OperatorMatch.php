<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Validation\Correspondence;

use SqlSemantics\Statement\Construction\Expression as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Validation\Check;

/**
 * Concrete operation checks and strict operand-slot correspondence for scalar operators.
 * @visibility SqlSemantics
 */
final class OperatorMatch
{
    /**
     * Produces work only for real operands after verifying the parent's concrete operation.
     * @return list<ScalarPair>
     */
    public function children(ScalarPair $pair): array
    {
        $input = $pair->input;
        $actual = $pair->actual;
        $scope = $pair->scope;
        if ($input instanceof C\UnaryInput) {
            Check::invariant($actual instanceof E\SqliteUnary && $input->operator === $actual->operator, 'A unary request must retain its actual operator.');
            return [new ScalarPair($input->operand, $actual->operand, $scope)];
        }
        if ($input instanceof C\BinaryInput) {
            Check::invariant($actual instanceof E\SqliteBinary && $input->operator === $actual->operator && SpellingMatch::same($input->layout, $actual->layout), 'A binary request must retain its operation and constrained output spelling.');
            return [new ScalarPair($input->left, $actual->left, $scope), new ScalarPair($input->right, $actual->right, $scope)];
        }
        if ($input instanceof C\GroupedInput) {
            Check::invariant($actual instanceof E\Rendering\GroupedExpression && $input->before === $actual->before && $input->after === $actual->after, 'Explicit grouping must retain its checked name-sensitive layout.');
            return [new ScalarPair($input->operand, $actual->operand, $scope)];
        }
        if ($input instanceof C\CastInput) {
            Check::invariant($actual instanceof E\Conversion\SqliteCast && $input->target === $actual->target, 'A cast must retain the requested conversion target.');
            return [new ScalarPair($input->operand, $actual->operand, $scope)];
        }
        Check::invariant($input instanceof C\CollationInput && $actual instanceof E\Conversion\SqliteCollated && NamesMatch::same($input->collation, $actual->collation), 'An explicit comparison collation must retain its requested name.');
        return [new ScalarPair($input->operand, $actual->operand, $scope)];
    }
}
