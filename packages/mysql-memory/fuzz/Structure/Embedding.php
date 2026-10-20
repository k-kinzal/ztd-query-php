<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use SqlSemantics\Platform\MySql\Rules\Query\WindowReferences;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Supplies a lexical query context for a freely generated scalar expression.
 *
 * The original scalar is retained by identity. Free window names receive empty
 * definitions and free columns receive relation occurrences, identically for direct execution and the reference rendering.
 * Complete statements and subqueries keep their own namespaces unchanged.
 * This never repairs or discards a generated statement or reads source SQL.
 */
final class Embedding
{
    /**
     * Places a scalar in a selection with its required ambient window definitions.
     */
    public function statement(Statement|Scalar|Query $node): Statement
    {
        if ($node instanceof Statement) {
            return $node;
        }
        if ($node instanceof Query) {
            return new QueryRoot($node);
        }
        $windows = [];
        foreach ((new WindowReferences())->names([$node]) as $name) {
            $windows[strtolower($name->value)] ??= new WindowDefinition($name, new WindowSpec());
        }
        return new QueryRoot(new Select([], [new SelectExpression($node)], from: (new Context\Columns())->input($node), windows: array_values($windows)));
    }
}
