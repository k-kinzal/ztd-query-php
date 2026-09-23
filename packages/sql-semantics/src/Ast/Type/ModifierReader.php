<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Checks PostgreSQL's type-modifier input boundary without evaluating expressions.
 * @visibility SqlSemantics
 */
final class ModifierReader
{
    /**
     * Rejects general expressions where PostgreSQL requires simple constants or identifiers.
     * @throws InvalidSql
     */
    public static function validate(Node $source): void
    {
        foreach (Tree::outer($source, ['expr_list', 'func_arg_list']) as $list) {
            foreach (Tree::outer($list, ['a_expr']) as $argument) {
                if (self::form($argument) === null) {
                    throw new InvalidSql(InputViolation::TypeModifier, $argument);
                }
            }
        }
    }

    /**
     * Parentheses and numeric negation retain constant identity; other operators do not.
     */
    public static function form(Node|Token $source): ?ModifierForm
    {
        if ($source instanceof Token) {
            return match ($source->name) {
                'ICONST', 'FCONST' => ModifierForm::Number,
                'SCONST', 'USCONST' => ModifierForm::Text,
                default => null,
            };
        }
        if ($source->name === 'Sconst') {
            return ModifierForm::Text;
        }
        if ($source->name === 'columnref') {
            return Tree::child($source, ['indirection', 'opt_indirection']) === null ? ModifierForm::Identifier : null;
        }
        $children = Tree::significant($source);
        if (count($children) === 1) {
            return self::form($children[0]);
        }
        if (count($children) === 3 && $children[0] instanceof Token && $children[0]->text === '(' && $children[2] instanceof Token && $children[2]->text === ')') {
            return self::form($children[1]);
        }
        if (count($children) === 2 && $children[0] instanceof Token && $children[0]->text === '-' && self::form($children[1]) === ModifierForm::Number) {
            return ModifierForm::Number;
        }
        return null;
    }
}
