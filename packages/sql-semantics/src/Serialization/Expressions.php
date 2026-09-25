<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**

 * Serializes the operands of classified expressions. @visibility SqlSemantics

 */
final class Expressions
{
    /**
     * @throws InvalidStructure
     */
    public static function write(Expression $value): Tree
    {
        return \SqlSemantics\Serialization\Scalar\IntrinsicExpressions::write($value) ?? match (true) {
            $value instanceof Scalar\Value\ContextReference,
            $value instanceof Scalar\Value\Literal,
            $value instanceof Scalar\Value\ConfigurationIdentifier,
            $value instanceof Scalar\Value\ConfigurationKeyword,
            $value instanceof Scalar\Value\RowExpression,
            $value instanceof Scalar\Value\IntroducedLiteral,
            $value instanceof Scalar\Value\TemporalLiteral,
            $value instanceof Scalar\Value\ArrayConstructor => \SqlSemantics\Serialization\Scalar\ValueExpressions::write($value),
            $value instanceof \SqlSemantics\Model\Query\Inspection\MetadataColumn => \SqlSemantics\Model\Sql\Build::identifier([$value->label], \SqlSemantics\Dialect::MySql),
            $value instanceof Scalar\Reference\LocalVariableReference => \SqlSemantics\Model\Sql\Build::identifier([$value->variable->name], \SqlSemantics\Dialect::MySql),
            $value instanceof Scalar\Reference\TriggerColumn,
            $value instanceof Scalar\Reference\ColumnReference,
            $value instanceof Scalar\Reference\UnresolvedColumnReference,
            $value instanceof Scalar\Reference\Wildcard,
            $value instanceof Scalar\Reference\Parameter,
            $value instanceof Scalar\Reference\CursorPosition,
            $value instanceof Scalar\Reference\FieldAccess,
            $value instanceof Scalar\Reference\ElementAccess,
            $value instanceof Scalar\Reference\SliceAccess,
            $value instanceof Scalar\Reference\VariableReference,
            $value instanceof Scalar\Reference\UnresolvedVariableReference,
            $value instanceof Scalar\Reference\VariableAssignment,
            $value instanceof Scalar\Reference\JsonPathExtraction,
            $value instanceof Scalar\Composite\RowExpansion,
            $value instanceof Scalar\Reference\ProposedColumn => \SqlSemantics\Serialization\Scalar\ReferenceExpressions::write($value),
            $value instanceof Scalar\Operator\CollatedExpression,
            $value instanceof Scalar\Operator\BinaryExpression,
            $value instanceof Scalar\Operator\UnaryExpression,
            $value instanceof Scalar\Operator\ArrayCast,
            $value instanceof Scalar\Operator\TimeZoneCast,
            $value instanceof Scalar\Operator\CharacterSetConversion,
            $value instanceof Scalar\Operator\CastExpression => \SqlSemantics\Serialization\Scalar\OperatorExpressions::write($value),
            $value instanceof Scalar\Text\CharacterCodes,
            $value instanceof Scalar\Text\WeightString,
            $value instanceof Scalar\Text\InternalWeightString => \SqlSemantics\Serialization\Scalar\TextExpressions::codes($value),
            $value instanceof Scalar\Operator\Qualified\QualifiedInfixOperation,
            $value instanceof Scalar\Operator\Qualified\QualifiedPrefixOperation => \SqlSemantics\Serialization\Scalar\OperatorExpressions::named($value),
            default => self::composite($value),
        };
    }

    /**
     * Writes conditional, subquery and function expressions, which contain further expressions.
     * @throws InvalidStructure
     */
    public static function composite(Expression $value): Tree
    {
        return match (true) {
            $value instanceof Scalar\Conditional\JsonPredicate => \SqlSemantics\Serialization\Scalar\ConditionalExpressions::json($value),
            $value instanceof Scalar\Conditional\JsonMembership,
            $value instanceof Scalar\Conditional\Extremum,
            $value instanceof Scalar\Conditional\Coalesce,
            $value instanceof Scalar\Conditional\NullIf,
            $value instanceof Scalar\Conditional\Between,
            $value instanceof Scalar\Conditional\InList,
            $value instanceof Scalar\Conditional\PatternMatch,
            $value instanceof Scalar\Conditional\SimpleCase,
            $value instanceof Scalar\Conditional\SearchedCase,
            $value instanceof Scalar\Conditional\ArrayComparison => \SqlSemantics\Serialization\Scalar\ConditionalExpressions::write($value),
            $value instanceof Scalar\Query\RowSubquery,
            $value instanceof Scalar\Query\ScalarSubquery,
            $value instanceof Scalar\Query\ExistsSubquery,
            $value instanceof Scalar\Query\InSubquery,
            $value instanceof Scalar\Query\QuantifiedComparison,
            $value instanceof Scalar\Query\ArraySubquery => \SqlSemantics\Serialization\Scalar\Subqueries::write($value),
            $value instanceof Scalar\Function\FunctionCall,
            $value instanceof Scalar\Function\AggregateCall,
            $value instanceof Scalar\Function\AllRowsAggregate,
            $value instanceof Scalar\Function\OrderedSetCall,
            $value instanceof Scalar\Function\WindowCall => \SqlSemantics\Serialization\Scalar\Functions::write($value),
            $value instanceof Scalar\Function\Argument\NamedArgument,
            $value instanceof Scalar\Function\Argument\VariadicArgument => \SqlSemantics\Serialization\Scalar\Functions::argument($value),
            default => throw new InvalidStructure('This expression has no standalone SQL form: ' . $value::class),
        };
    }
}
