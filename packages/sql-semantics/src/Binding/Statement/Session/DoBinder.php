<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Session;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Execution\DoExpressionsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds MySQL expressions whose evaluation produces no returned result set.
 * @visibility SqlSemantics
 */
final class DoBinder
{
    /**
     * Retains operand order and ignores SELECT-item labels that DO does not expose.
     * @throws InvalidSql
     */
    public static function bind(Origin $origin, Node $node, Scope $scope): ?DoExpressionsStatement
    {
        if ($origin->dialect !== Dialect::MySql || strtoupper($node->tokens()[0]->text ?? '') !== 'DO') {
            return null;
        }
        if (array_filter(Tree::outer($node, ['expr', 'table_wild']), static fn (Node $part): bool => $part->name === 'table_wild') !== []) {
            throw new InvalidSql(InputViolation::DiscardedValue, $node);
        }
        $operands = array_map(static fn (Node $expression) => (new ExpressionBinder())->bind($expression, $scope), Tree::outer($node, ['expr']));
        try {
            return new DoExpressionsStatement($origin, Collections::nonEmpty($operands));
        } catch (InvalidStructure $error) {
            throw new InvalidSql(InputViolation::DiscardedValue, $node, $error);
        }
    }
}
