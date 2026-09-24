<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Document;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Xml;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\TableFunction\Xml\PassingMode;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes PostgreSQL's SQL/XML predicates, conversions and constructors in their own syntax from their typed operands.
 * @visibility SqlSemantics
 */
final class XmlExpressions
{
    /**
     * Leaves every other expression to the scalar serializer.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function write(Expression $value): ?Tree
    {
        return match (true) {
            $value instanceof Xml\DocumentPredicate => Build::parentheses(new Tree('xml-document', [Expressions::write($value->value), Build::keyword($value->spelling())])),
            $value instanceof Xml\XmlExistence => self::call('XMLEXISTS', [Expressions::write($value->path), Build::keyword('PASSING'), ...self::mode($value->inputMode), Expressions::write($value->document), ...self::mode($value->outputMode)]),
            $value instanceof Xml\XmlParse => self::call('XMLPARSE', [Build::keyword($value->option->value), Expressions::write($value->value), ...($value->preserveWhitespace ? [Build::keyword('PRESERVE WHITESPACE')] : [])]),
            $value instanceof Xml\XmlSerialization => self::call('XMLSERIALIZE', [Build::keyword($value->option->value), Expressions::write($value->value), Build::keyword('AS'), TypeDeclaration::write($value->target), ...($value->indent ? [Build::keyword('INDENT')] : [])]),
            $value instanceof Xml\XmlRoot => self::call('XMLROOT', [Build::separated([Expressions::write($value->value), new Tree('xml-version', [Build::keyword('VERSION'), $value->version === null ? Build::keyword('NO VALUE') : Expressions::write($value->version)]), ...($value->standalone === Xml\XmlStandalone::Omitted ? [] : [Build::keyword('STANDALONE ' . $value->standalone->value)])])]),
            default => self::composite($value),
        };
    }

    /**
     * Writes XMLELEMENT, XMLFOREST, XMLPI and XMLCONCAT, leaving every other expression to the scalar serializer.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function composite(Expression $value): ?Tree
    {
        return match (true) {
            $value instanceof Xml\XmlElement => self::call('XMLELEMENT', [Build::separated([self::name($value->name), ...($value->attributes === [] ? [] : [new Tree('xml-attributes', [Build::keyword('XMLATTRIBUTES'), Build::parentheses(self::named($value->attributes))])]), ...array_map(Expressions::write(...), $value->content)])]),
            $value instanceof Xml\XmlForest => self::call('XMLFOREST', [self::named($value->elements)]),
            $value instanceof Xml\XmlProcessingInstruction => self::call('XMLPI', [Build::separated([self::name($value->target), ...($value->content === null ? [] : [Expressions::write($value->content)])])]),
            $value instanceof Xml\XmlConcatenation => self::call('XMLCONCAT', [Build::separated(array_map(Expressions::write(...), $value->values))]),
            default => null,
        };
    }

    /**
     * Writes the keyword and its parenthesized operands.
     * @param list<Tree> $parts
     */
    public static function call(string $keyword, array $parts): Tree
    {
        return new Tree('sql-xml', [Build::keyword($keyword), Build::parentheses(new Tree('sql-xml-arguments', $parts))]);
    }

    /**
     * Writes `NAME identifier`.
     */
    public static function name(string $name): Tree
    {
        return new Tree('xml-name', [Build::keyword('NAME'), Build::identifier([$name], Dialect::PostgreSql)]);
    }

    /**
     * Writes values with their aliases; a value named by its column is written without one.
     * @param list<Xml\XmlNamedArgument> $values
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function named(array $values): Tree
    {
        return Build::separated(array_map(static fn (Xml\XmlNamedArgument $value): Tree => new Tree('xml-named-value', [Expressions::write($value->value), ...($value->alias === null ? [] : [Build::keyword('AS'), Build::identifier([$value->alias], Dialect::PostgreSql)])]), $values));
    }

    /**
     * Writes an explicit passing mode.
     * @return list<Tree>
     */
    public static function mode(PassingMode $mode): array
    {
        return $mode === PassingMode::Default ? [] : [Build::keyword($mode->value)];
    }
}
