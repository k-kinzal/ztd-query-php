<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Preparation;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Program\Expression\RowUse;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Prepares the original JSON_TABLE document in its lateral input scope.
 * Subquery dependencies bind before the table function refuses their use.
 *
 * @visibility MySqlMemory
 */
final class JsonDocument
{
    /**
     * @throws SqlError When the document cannot bind or contains a subquery
     */
    public function compile(JsonTable $table, Compiler $compiler, Scope $scope): Evaluable
    {
        $scope = clone $scope;
        $scope->clause = 'a table function argument';
        $document = $table->document;
        if ($document instanceof ScalarSubquery || $document instanceof RowSubquery) {
            $compiler->planner->query($document->query, $scope);
            throw StatementError::WrongArguments->error('JSON_TABLE');
        }
        if (!$document instanceof Scalar) {
            (new RowUse())->prepare($document, $compiler, $scope);
            throw (new RowUse())->error($document);
        }
        $value = $compiler->compile($document, $scope);
        if ((new Walker())->find($document, Query::class) !== []) {
            throw StatementError::WrongArguments->error('JSON_TABLE');
        }
        return $value;
    }
}
