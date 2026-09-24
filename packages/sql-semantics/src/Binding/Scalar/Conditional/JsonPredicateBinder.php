<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Conditional;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Model\Scalar\Conditional\JsonItemKind;
use SqlSemantics\Model\Scalar\Conditional\JsonPredicate;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * Binds the PostgreSQL IS [NOT] JSON predicate with its item type and key uniqueness.
 * @visibility SqlSemantics
 */
final class JsonPredicateBinder
{
    /**
     * Whether a value of this type can be tested, as text, JSON or bytes; user-defined and unknown types are accepted.
     */
    public static function textual(\SqlSemantics\Type\TypeDescriptor $type): bool
    {
        $identity = $type->identity;
        if ($identity instanceof \SqlSemantics\Type\Identity\BuiltinIdentity) {
            return in_array($identity->value, ['unknown', 'dynamic', 'never', 'char', 'varchar', 'text', 'json', 'jsonb', 'bytea', 'bpchar', 'name'], true);
        }
        return !$identity instanceof \SqlSemantics\Type\Identity\Numeric\IntegerStorage && !$identity instanceof \SqlSemantics\Type\Identity\Numeric\NumericStorage && !$identity instanceof \SqlSemantics\Type\Identity\TemporalStorage && !$identity instanceof \SqlSemantics\Type\Identity\IntervalStorage && !$identity instanceof \SqlSemantics\Type\Identity\ArrayStorage;
    }

    /**
     * Returns the predicate when the node is `value IS [NOT] JSON ...`, or null otherwise.
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(Node $node, Scope $scope): ?JsonPredicate
    {
        $constraint = Tree::child($node, ['json_predicate_type_constraint']);
        $value = $node->children[0] ?? null;
        if ($constraint === null || !$value instanceof Node) {
            return null;
        }
        $operand = (new ExpressionBinder())->bind($value, $scope);
        if (!self::textual($operand->type)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::JsonPredicateOperand, $value);
        }
        $words = array_map(static fn ($token): string => strtoupper($token->text), $constraint->tokens());
        $uniqueness = Tree::child($node, ['json_key_uniqueness_constraint_opt']);
        $nullability = NullFacts::strict([$operand]);
        return new JsonPredicate(
            new ExpressionFacts((new TypeResolution($scope->identifiers->dialect, $scope->diagnostics()))->boolean(), $nullability, NullFacts::extensions([$operand], $nullability)),
            $node,
            $operand,
            JsonItemKind::from($words[1] ?? 'VALUE'),
            in_array('NOT', array_map(static fn ($child): string => strtoupper(Tree::text($child)), array_slice($node->children, 1)), true),
            $uniqueness !== null && strtoupper($uniqueness->tokens()[0]->text ?? '') === 'WITH',
        );
    }
}
