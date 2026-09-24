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
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Operator;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\Numeric\IntegerStorage;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds MySQL's CAST and CONVERT forms: typed casts, array casts, time zone casts and character set conversion.
 * @visibility SqlSemantics
 */
final class CastBinder
{
    /**
     * Recognizes CAST and CONVERT by their keyword and operand layout.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $first = $source->children[0] ?? null;
        $operand = Tree::child($source, ['expr']);
        if ($scope->identifiers->dialect !== Dialect::MySql || !$first instanceof Token || !in_array(strtoupper($first->text), ['CAST', 'CONVERT'], true) || $operand === null) {
            return null;
        }
        $value = (new ExpressionBinder())->bind($operand, $scope);
        $words = array_map(static fn (Node|Token $child): string => $child->name, Tree::significant($source));
        $charset = Tree::child($source, ['charset_name']);
        if ($charset !== null) {
            return new Operator\CharacterSetConversion($source, $value, strtolower(trim(Tree::text($charset), "`'\"")));
        }
        if (in_array('LOCAL_SYM', $words, true)) {
            throw new InvalidSql(InputViolation::CastConversion, $source);
        }
        $zone = Tree::child($source, ['TEXT_STRING_literal']);
        if ($zone !== null) {
            $literal = (new ExpressionBinder())->bind($zone, $scope);
            $precision = Tree::child($source, ['type_datetime_precision']);
            $digits = $precision === null ? '' : trim(Tree::text($precision), '() ');
            return $literal instanceof Literal ? new Operator\TimeZoneCast($source, $value, $literal, Tree::outer($source, ['opt_interval']) !== [] && Tree::hasTokens(Tree::outer($source, ['opt_interval'])[0]), $digits === '' ? null : (int) $digits) : null;
        }
        $target = Tree::child($source, ['cast_type']);
        if ($target === null) {
            return null;
        }
        $type = self::target($target);
        $array = Tree::child($source, ['opt_array_cast']);
        if ($array !== null && Tree::hasTokens($array)) {
            return new Operator\ArrayCast($source, $value, $type);
        }
        return new Operator\CastExpression(new ExpressionFacts($type, $value->nullability, $value->nullExtendedBy), $source, $value, Operator\CastMode::Explicit);
    }

    /**
     * Reads a cast target; SIGNED and UNSIGNED [INT] name 64-bit integers.
     */
    public static function target(Node $target): TypeDescriptor
    {
        $first = strtoupper(Tree::text(Tree::significant($target)[0] ?? $target));
        if (in_array($first, ['SIGNED', 'UNSIGNED'], true)) {
            return new TypeDescriptor(Dialect::MySql, new IntegerStorage(BuiltinIdentity::BigInt, null, $first === 'UNSIGNED'));
        }
        return (new TypeReader(Dialect::MySql))->read($target);
    }
}
