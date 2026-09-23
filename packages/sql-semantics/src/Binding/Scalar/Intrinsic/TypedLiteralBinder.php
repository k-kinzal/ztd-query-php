<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Intrinsic;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator\CastExpression;
use SqlSemantics\Model\Scalar\Operator\CastMode;
use SqlSemantics\Type\Identity\NamedIdentity;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds PostgreSQL typed constants as explicit conversions of their literal operand.
 * @visibility SqlSemantics
 */
final class TypedLiteralBinder
{
    /**
     * Retains type modifiers and interval fields without converting the literal's value.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Node $source, Scope $scope): ?CastExpression
    {
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || $source->name !== 'AexprConst') {
            return null;
        }
        $literal = Tree::child($source, ['Sconst']);
        $type = Tree::child($source, ['ConstTypename', 'ConstInterval', 'func_name']);
        if ($literal === null || $type === null) {
            return null;
        }
        if (Tree::child($source, ['opt_sort_clause']) !== null) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::TypeModifier, $source);
        }
        $reader = new TypeReader(Dialect::PostgreSql);
        $declaration = new Node('literal_type', 0, array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child !== $literal)));
        $result = $type->name === 'func_name' ? self::named($type, $source, $declaration, $scope) : $reader->read($declaration);
        $operand = (new ExpressionBinder())->bind($literal, $scope);
        return new CastExpression(new ExpressionFacts($result, $operand->nullability), $source, $operand, CastMode::Explicit);
    }

    /**
     * Retains user-defined type names and their classified type-input operands.
     */
    public static function named(Node $name, Node $source, Node $declaration, Scope $scope): TypeDescriptor
    {
        $parts = $scope->identifiers->parts($name);
        $first = $name->tokens()[0];
        $reader = new TypeReader(Dialect::PostgreSql);
        if (count($parts) === 1 && !str_starts_with($first->text, '"') && ($reader->canonical(strtoupper($parts[0])) !== null || \SqlSemantics\Type\Identity\BuiltinIdentity::tryFrom($parts[0]) !== null)) {
            return $reader->read($declaration);
        }
        $modifiers = Tree::child($source, ['func_arg_list']);
        $arguments = $modifiers === null ? [] : \SqlSemantics\Ast\Type\ModifierBinder::parameters($modifiers);
        return new TypeDescriptor(Dialect::PostgreSql, new NamedIdentity(new QualifiedName($parts), $arguments));
    }
}
