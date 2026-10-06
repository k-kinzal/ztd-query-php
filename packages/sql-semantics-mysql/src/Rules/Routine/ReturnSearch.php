<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\RepeatLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SimpleCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\WhileLoop;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Statement\Node;

/**
 * Finds out whether the body of a stored function holds a RETURN statement.
 *
 * Rule: MYSQL-FUNCTION-RETURN-001. The server rejects a stored function
 * whose body holds no RETURN statement at all (ER_SP_NORETURN); whether
 * every path reaches one is found only when the function runs. The search
 * visits the statements nested in blocks, handlers, conditionals and loops
 * with an explicit stack. Terminates: every statement is visited once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/return.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReturnSearch
{
    /**
     * Tells whether a statement is or contains a RETURN statement.
     */
    public function found(Node $statement): bool
    {
        $pending = [$statement];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current instanceof ReturnStatement) {
                return true;
            }
            array_push($pending, ...$this->nested($current));
        }

        return false;
    }

    /**
     * Answers the statements directly nested in a statement.
     *
     * @return list<Node>
     */
    public function nested(Node $statement): array
    {
        if ($statement instanceof Block) {
            $nested = $statement->statements;
            foreach ($statement->declarations as $declaration) {
                if ($declaration instanceof HandlerDeclaration) {
                    $nested[] = $declaration->statement;
                }
            }

            return $nested;
        }
        if ($statement instanceof IfStatement || $statement instanceof SimpleCase || $statement instanceof SearchedCase) {
            $nested = $statement->otherwise;
            foreach ($statement->branches as $branch) {
                array_push($nested, ...$branch->statements);
            }

            return $nested;
        }

        return $statement instanceof Loop || $statement instanceof WhileLoop || $statement instanceof RepeatLoop ? $statement->statements : [];
    }
}
