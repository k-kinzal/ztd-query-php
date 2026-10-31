<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Operators;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Evaluates scalar conditions required before accepting a prepared statement.
 * The caller supplies the reached semantic nodes and their bound compiler.
 *
 * @visibility MySqlMemory
 */
final class JsonNulls
{
    /**
     * Reads statement-constant JSON null tests in MySQL 5.7. Grouping does not
     * repeat a test; subquery values and row-dependent values remain unread.
     *
     * @param list<Node> $nodes The reached semantic nodes
     * @throws \MySqlMemory\Error\SqlError When a current JSON document is invalid
     */
    public function check(Compiler $compiler, array $nodes): void
    {
        if ($compiler->settings->release() !== GrammarRelease::MySql5744) {
            return;
        }
        $operators = new Operators($compiler);
        foreach ($nodes as $node) {
            if (!$node instanceof Scalar || $node instanceof Grouped) {
                continue;
            }
            $test = $operators->tested($node);
            if ($test === null || $test[1] || $compiler->constancy($test[0]) !== Constancy::Statement || $compiler->domain($test[0])->kind !== Kind::Json || (new Walker())->find($test[0], Query::class) !== []) {
                continue;
            }
            $operators->nullness($test[0], false, new Scope(), $node);
        }
    }
}
