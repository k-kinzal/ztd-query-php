<?php

declare(strict_types=1);

namespace SqlSemantics\Ast\Type;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\LiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;
use SqlSemantics\Type\Modifier\IdentifierParameter;
use SqlSemantics\Type\Modifier\NegatedParameter;
use SqlSemantics\Type\Modifier\TextParameter;

/**
 * Reads PostgreSQL type-input operands without binding them as row expressions.
 * @visibility SqlSemantics
 */
final class ModifierBinder
{
    /**
     * @return list<NumericParameter|TextParameter|IdentifierParameter|NegatedParameter>
     * @throws InvalidSql
     */
    public static function parameters(Node $source): array
    {
        ModifierReader::validate($source);
        foreach (Tree::outer($source, ['func_arg_expr']) as $argument) {
            if (count(Tree::significant($argument)) !== 1) {
                throw new InvalidSql(InputViolation::TypeModifier, $argument);
            }
        }
        return array_map(self::read(...), Tree::outer($source, ['a_expr']));
    }

    /**
     * Parentheses do not change operand identity; negations remain explicit operations.
     * @throws InvalidSql
     */
    public static function read(Node|Token $source): NumericParameter|TextParameter|IdentifierParameter|NegatedParameter
    {
        if ($source instanceof Token) {
            if (in_array($source->name, ['ICONST', 'FCONST'], true)) {
                return new NumericParameter($source->text);
            }
            $value = (new LiteralBinder(Dialect::PostgreSql))->bind($source);
            if ($value instanceof Literal && ModifierReader::form($source) === ModifierForm::Text) {
                return new TextParameter($value);
            }
            throw new InvalidSql(InputViolation::TypeModifier, $source);
        }
        if ($source->name === 'columnref' && ModifierReader::form($source) === ModifierForm::Identifier) {
            return new IdentifierParameter((new Identifiers(Dialect::PostgreSql))->name($source->tokens()[0]));
        }
        $children = Tree::significant($source);
        if (count($children) === 1) {
            return self::read($children[0]);
        }
        if (count($children) === 3 && $children[0] instanceof Token && $children[0]->text === '(' && $children[2] instanceof Token && $children[2]->text === ')') {
            return self::read($children[1]);
        }
        if (count($children) === 2 && $children[0] instanceof Token && $children[0]->text === '-') {
            $operand = self::read($children[1]);
            if ($operand instanceof NumericParameter || $operand instanceof NegatedParameter) {
                return new NegatedParameter($operand);
            }
        }
        throw new InvalidSql(InputViolation::TypeModifier, $source);
    }
}
