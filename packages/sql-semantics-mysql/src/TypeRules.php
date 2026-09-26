<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Binding\TypeResolution;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\Operator;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\TypeDeclaration;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Core\Type\TypeName;

/**
 * MySql TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    private const SUPPORTED = [
        Builtin::Unknown,
        Builtin::TinyInt,
        Builtin::SmallInt,
        Builtin::MediumInt,
        Builtin::Integer,
        Builtin::BigInt,
        Builtin::Numeric,
        Builtin::Real,
        Builtin::DoublePrecision,
        Builtin::Bit,
        Builtin::Char,
        Builtin::VarChar,
        Builtin::TinyText,
        Builtin::Text,
        Builtin::MediumText,
        Builtin::LongText,
        Builtin::Enum,
        Builtin::Set,
        Builtin::Binary,
        Builtin::VarBinary,
        Builtin::TinyBlob,
        Builtin::Blob,
        Builtin::MediumBlob,
        Builtin::LongBlob,
        Builtin::Date,
        Builtin::Time,
        Builtin::DateTime,
        Builtin::Timestamp,
        Builtin::Year,
        Builtin::Json,
        Builtin::Vector,
        Builtin::Geometry,
        Builtin::GeometryCollection,
        Builtin::Point,
        Builtin::MultiPoint,
        Builtin::LineString,
        Builtin::MultiLineString,
        Builtin::Polygon,
        Builtin::MultiPolygon,
    ];

    private const INTEGERS = [Builtin::TinyInt, Builtin::SmallInt, Builtin::MediumInt, Builtin::Integer, Builtin::BigInt];

    private const NUMERIC_RANK = [Builtin::TinyInt, Builtin::SmallInt, Builtin::MediumInt, Builtin::Integer, Builtin::BigInt, Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision];

    private const STRINGS = [Builtin::Char, Builtin::VarChar, Builtin::TinyText, Builtin::Text, Builtin::MediumText, Builtin::LongText];

    /**
     * Retains the language identity used in semantic output.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type by its keyword tokens, arguments and attributes.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader($this->dialect))->read($node, $values);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }

    /**
     * Types a literal terminal without converting its contents; an unsigned 64-bit literal keeps its sign fact.
     */
    public function literal(Token $token): ?TypeDescriptor
    {
        $name = $token->name;
        $number = str_replace('_', '', $token->text);
        if (in_array($name, ['ICONST', 'FCONST', 'INTEGER', 'NUM', 'LONG_NUM', 'ULONGLONG_NUM'], true) && preg_match('/^0[xob]/i', $number) === 1) {
            Tree::unsupported($token, 'non-decimal numeric literal');
        }
        $text = strtoupper($token->text);
        $type = match (true) {
            in_array($name, ['ICONST', 'NUM', 'INTEGER'], true) => $this->integer($number),
            in_array($name, ['LONG_NUM', 'ULONGLONG_NUM'], true) => Builtin::BigInt,
            $name === 'FCONST' => ctype_digit($number) ? $this->integer($number) : Builtin::Numeric,
            $name === 'DECIMAL_NUM' => Builtin::Numeric,
            in_array($name, ['FLOAT_NUM', 'FLOAT'], true) => Builtin::DoublePrecision,
            in_array($name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true) => Builtin::Text,
            in_array($name, ['NULL_P', 'NULL_SYM', 'NULL'], true) => Builtin::Unknown,
            in_array($text, ['TRUE', 'FALSE'], true) && !in_array($name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) => Builtin::Integer,
            default => null,
        };

        return $type === null ? null : new TypeDescriptor($this->dialect, $type, unsigned: $name === 'ULONGLONG_NUM');
    }

    /**
     * Every decimal integer literal within the signed 64-bit range that the lexer classifies as a plain number is an INT.
     */
    public function integer(string $text): Builtin
    {
        return Builtin::Integer;
    }

    /**
     * @param list<Expression> $expressions
     * @throws SemanticException
     */
    public function common(array $expressions, Node|Token $source): TypeDescriptor
    {
        $types = [];
        foreach ($expressions as $expression) {
            if (!$expression->type->is(Builtin::Unknown)) {
                $types[] = $expression->type;
            }
        }
        if ($types === []) {
            return new TypeDescriptor($this->dialect, Builtin::Unknown);
        }
        $names = [];
        foreach ($types as $type) {
            if (!in_array($type->name, $names, true)) {
                $names[] = $type->name;
            }
        }
        if (count($names) === 1) {
            return new TypeDescriptor($this->dialect, $types[0]->name, affinity: $types[0]->affinity);
        }
        $rank = -1;
        foreach ($names as $name) {
            $index = array_search($name, self::NUMERIC_RANK, true);
            $rank = $index === false ? PHP_INT_MIN : max($rank, $index);
        }
        if ($rank >= 0) {
            return new TypeDescriptor($this->dialect, self::NUMERIC_RANK[$rank]);
        }
        if (count(array_filter($names, static fn (Builtin|TypeName $name): bool => in_array($name, self::STRINGS, true))) === count($names)) {
            return new TypeDescriptor($this->dialect, Builtin::Text);
        }
        throw new SemanticException('unsupported-coercion', 'Cannot establish a common type for: ' . implode(', ', array_map(static fn (TypeDescriptor $type): string => $type->label(), $types)), $source);
    }

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor
    {
        return new TypeDescriptor($this->dialect, Builtin::Integer);
    }

    /**
     * Integer arithmetic is computed with 64-bit precision; an unsigned operand makes the result unsigned.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(Operator $operator, array $operands, Node $source): TypeDescriptor
    {
        $type = (new TypeResolution($this->dialect))->common($operands, $source);
        if (!in_array($type->name, self::INTEGERS, true)) {
            Tree::unsupported($source, 'arithmetic type');
        }
        $unsigned = false;
        foreach ($operands as $operand) {
            $unsigned = $unsigned || $operand->type->unsigned;
        }

        return new TypeDescriptor($this->dialect, Builtin::BigInt, unsigned: $unsigned);
    }

    /**
     * Accepts scalar truth values.
     */
    public function predicate(Expression $expression): void
    {
    }

    /**
     * @param list<Expression> $operands
     * @return list<Expression>
     */
    public function coalesce(array $operands, TypeDescriptor $type): array
    {
        return $operands;
    }

    /**
     * Resolves the output type of a projected literal.
     */
    public function project(Expression $expression): Expression
    {
        return $expression;
    }
}
