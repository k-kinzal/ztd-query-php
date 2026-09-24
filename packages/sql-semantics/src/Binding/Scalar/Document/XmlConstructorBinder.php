<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Document;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Xml;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Type\Nullability;

/**
 * Binds PostgreSQL's XML constructors: XMLELEMENT, XMLFOREST, XMLPI and XMLCONCAT.
 * @visibility SqlSemantics
 */
final class XmlConstructorBinder
{
    /**
     * Binds the element name, its attributes and its content; an element is never NULL.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function element(Node $source, Scope $scope): Xml\XmlElement
    {
        $attributeList = Tree::child($source, ['xml_attributes']);
        $attributes = $attributeList === null ? [] : array_map(static fn (Node $attribute): Xml\XmlNamedArgument => self::named($attribute, $scope), Tree::outer($attributeList, ['xml_attribute_el']));
        $labels = array_map(static fn (Xml\XmlNamedArgument $attribute): string => $attribute->label(), $attributes);
        if (count(array_unique($labels)) !== count($labels)) {
            throw new InvalidSql(InputViolation::XmlValueName, $attributeList ?? $source);
        }
        $content = self::values($source, $scope);
        $operands = [...array_map(static fn (Xml\XmlNamedArgument $attribute): Expression => $attribute->value, $attributes), ...$content];
        return new Xml\XmlElement(XmlBinder::facts('xml', $operands, Nullability::NotNull), $source, self::label($source, $scope), $attributes, $content);
    }

    /**
     * Binds the forest elements; the result is NULL only when every value is.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function forest(Node $source, Scope $scope): Xml\XmlForest
    {
        $elements = array_map(static fn (Node $element): Xml\XmlNamedArgument => self::named($element, $scope), Tree::outer($source, ['xml_attribute_el']));
        $values = array_map(static fn (Xml\XmlNamedArgument $element): Expression => $element->value, $elements);
        return new Xml\XmlForest(XmlBinder::facts('xml', $values, NullFacts::coalesce($values)), $source, $elements);
    }

    /**
     * Binds the instruction target and its optional content; the result is NULL when the content is.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function instruction(Node $source, Scope $scope): Xml\XmlProcessingInstruction
    {
        $contentNode = Tree::child($source, ['a_expr']);
        $content = $contentNode === null ? null : (new ExpressionBinder())->bind($contentNode, $scope);
        $operands = $content === null ? [] : [$content];
        return new Xml\XmlProcessingInstruction(XmlBinder::facts('xml', $operands, NullFacts::strict($operands)), $source, self::label($source, $scope), $content);
    }

    /**
     * Binds the concatenated values; the result is NULL only when every value is.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function concatenation(Node $source, Scope $scope): Xml\XmlConcatenation
    {
        $values = self::values($source, $scope);
        return new Xml\XmlConcatenation(XmlBinder::facts('xml', $values, NullFacts::coalesce($values)), $source, $values);
    }

    /**
     * Binds a value with its alias; a value without one must reference a column or a whole row.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function named(Node $source, Scope $scope): Xml\XmlNamedArgument
    {
        $value = (new ExpressionBinder())->bind(Tree::child($source, ['a_expr']) ?? Tree::invalid($source, 'XML value'), $scope);
        $alias = Tree::child($source, ['ColLabel']);
        if ($alias === null && (!$value instanceof ColumnReference && !$value instanceof UnresolvedColumnReference && !$value instanceof Wildcard || $value->referenceParts() === [])) {
            throw new InvalidSql(InputViolation::XmlValueName, $source);
        }
        return new Xml\XmlNamedArgument($value, $alias === null ? null : $scope->identifiers->parts($alias)[0]);
    }

    /**
     * Reads the NAME label of XMLELEMENT or XMLPI.
     */
    public static function label(Node $source, Scope $scope): string
    {
        return $scope->identifiers->parts(Tree::child($source, ['ColLabel']) ?? Tree::invalid($source, 'XML name'))[0];
    }

    /**
     * Binds the expression list of XMLELEMENT content or XMLCONCAT.
     * @return list<Expression>
     */
    public static function values(Node $source, Scope $scope): array
    {
        $list = Tree::child($source, ['expr_list']);
        return $list === null ? [] : array_map(static fn (Node $value): Expression => (new ExpressionBinder())->bind($value, $scope), Tree::outer($list, ['a_expr']));
    }
}
