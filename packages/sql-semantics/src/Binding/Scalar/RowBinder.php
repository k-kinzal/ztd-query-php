<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\RowExpression;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Distinguishes row constructors from function calls and scalar parentheses.
 *
 * @visibility SqlSemantics
 */
final class RowBinder
{
    /**
     * Returns the ordered fields of an explicit or implicit row constructor.
     */
    public static function bind(Node $source, Scope $scope): ?RowExpression
    {
        $children = Tree::significant($source);
        $first = $children[0] ?? null;
        $explicit = $first instanceof Token && strtoupper($first->text) === 'ROW';
        $comma = array_filter($children, static fn (Node|Token $child): bool => $child instanceof Token && $child->text === ',') !== [];
        $implicit = $first instanceof Token && $first->text === '(' && $comma;
        if (!$explicit && !$implicit && !in_array($source->name, ['implicit_row', 'explicit_row'], true)) {
            return null;
        }
        $fields = Tree::outer($source, ['a_expr', 'expr']);
        if (count($fields) === 1 && $fields[0] === $source) {
            $fields = [];
            foreach ($source->children as $child) {
                if ($child instanceof Node) {
                    array_push($fields, ...Tree::outer($child, ['a_expr', 'expr']));
                }
            }
        }
        $items = array_map(static fn (Node $field) => (new ExpressionBinder())->bind($field, $scope), $fields);
        return new RowExpression(new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'record'), Nullability::NotNull), $source, $items);
    }
}
