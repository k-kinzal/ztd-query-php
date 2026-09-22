<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Write;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Write\DefaultSource;
use SqlSemantics\Model\Write\InputRow;

/**
 * Classifies storage inputs before ordinary expression binding.
 * @visibility SqlSemantics
 */
final class WriteInputs
{
    /**
     * Binds a value or an explicit destination-default instruction.
     */
    public static function value(Node $node, Scope $scope): Expression|DefaultSource
    {
        $tokens = $node->tokens();
        if (count($tokens) === 1 && in_array($tokens[0]->name, ['DEFAULT', 'DEFAULT_SYM'], true)) {
            return DefaultSource::Column;
        }
        return (new ExpressionBinder())->bind($node, $scope);
    }

    /**
     * Binds a row used on the right of a tuple assignment, including DEFAULT slots.
     */
    public static function tuple(Node $node, Scope $scope): ?InputRow
    {
        $current = $node;
        while (!in_array($current->name, ['implicit_row', 'explicit_row'], true)) {
            $children = Tree::significant($current);
            if (count($children) !== 1 || !$children[0] instanceof Node) {
                return null;
            }
            $current = $children[0];
        }
        return new InputRow($scope->identifiers->dialect, array_map(static fn (Node $value): Expression|DefaultSource => self::value($value, $scope), Tree::outer($current, ['a_expr'])));
    }

    /**
     * @return list<list<Expression|DefaultSource>>
     */
    public static function rows(Node $statement, Scope $scope): array
    {
        $rows = array_map(static fn (array $row): array => array_map(static fn (Node $value): Expression|DefaultSource => self::value($value, $scope), $row), \SqlSemantics\Binding\Statement\ValueRows::nodes($statement));
        if ($rows !== []) {
            \SqlSemantics\Binding\Statement\ValueRows::check($rows, $statement);
        }
        return $rows;
    }
}
