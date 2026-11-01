<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem\Location;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Plan\Window\Resolution;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Session\Locator;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Locates aggregate and window uses in UPDATE roles without a query grouping scope.
 * Nested queries retain their own grouping context.
 *
 * @visibility MySqlMemory
 */
final class WriteFunctions
{
    /**
     * Adds a preparation condition at the ungrouped call, before its arguments.
     *
     * @param array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}> $located
     * @return array<int, array{Diagnostic|FunctionCall|SqlError, array{string, list<int>}}>
     */
    public function located(Node $statement, Locator $locator, array $located): array
    {
        if (!$statement instanceof Update) {
            return $located;
        }
        $outer = array_fill_keys(array_map(spl_object_id(...), (new Walker())->find($statement, Scalar::class, false)), true);
        foreach ($locator->functions as [$call, $clause, $order]) {
            if (isset($outer[spl_object_id($call)])) {
                $error = Windowing::windowed($call) ? Resolution::unavailable($call, new \MySqlMemory\Evaluation\Scope()) : QueryError::InvalidGroupFunctionUse->error();
                $located[spl_object_id($error)] = [$error, [$clause, $order]];
            }
        }
        return $located;
    }
}
