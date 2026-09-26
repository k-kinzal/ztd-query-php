<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Binding\ExpressionRules;
use SqlSemantics\Core\Binding\TypeResolution;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\ExpressionKind;
use SqlSemantics\Core\Model\Operator;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\TypeDeclaration;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Core\Type\TypeName;

/**
 * PostgreSql TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    private const SUPPORTED = [
        Builtin::Unknown,
        Builtin::SmallInt, Builtin::Integer, Builtin::BigInt, Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision, Builtin::Money,
        Builtin::Boolean, Builtin::Bit, Builtin::BitVarying,
        Builtin::Char, Builtin::VarChar, Builtin::Text, Builtin::QuotedChar, Builtin::Name, Builtin::Bytea,
        Builtin::Date, Builtin::Time, Builtin::TimeTz, Builtin::Timestamp, Builtin::TimestampTz, Builtin::Interval,
        Builtin::Json, Builtin::Jsonb, Builtin::JsonPath, Builtin::Xml, Builtin::Uuid,
        Builtin::Point, Builtin::Line, Builtin::LineSegment, Builtin::Box, Builtin::Path, Builtin::Polygon, Builtin::Circle,
        Builtin::Inet, Builtin::Cidr, Builtin::MacAddr, Builtin::MacAddr8, Builtin::TsVector, Builtin::TsQuery,
        Builtin::Int4Range, Builtin::Int8Range, Builtin::NumRange, Builtin::TsRange, Builtin::TsTzRange, Builtin::DateRange,
        Builtin::Int4MultiRange, Builtin::Int8MultiRange, Builtin::NumMultiRange, Builtin::TsMultiRange, Builtin::TsTzMultiRange, Builtin::DateMultiRange,
        Builtin::Oid, Builtin::RegClass, Builtin::RegCollation, Builtin::RegConfig, Builtin::RegDictionary, Builtin::RegNamespace,
        Builtin::RegOper, Builtin::RegOperator, Builtin::RegProc, Builtin::RegProcedure, Builtin::RegRole, Builtin::RegType,
        Builtin::PgLsn, Builtin::PgSnapshot, Builtin::TxidSnapshot,
    ];

    private const INTEGERS = [Builtin::SmallInt, Builtin::Integer, Builtin::BigInt];

    private const NUMERIC_RANK = [Builtin::SmallInt, Builtin::Integer, Builtin::BigInt, Builtin::Numeric, Builtin::Real, Builtin::DoublePrecision];

    private const STRINGS = [Builtin::Char, Builtin::VarChar, Builtin::Text];

    /**
     * Retains the language identity used in semantic output.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type by its grammar production and the catalog names it refers to.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader($this->dialect))->read($node);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }

    /**
     * Types a literal terminal without converting its contents; a string literal stays unknown until context resolves it.
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
            $name === 'FCONST' => ctype_digit($number) ? $this->integer($number) : Builtin::Numeric,
            in_array($name, ['FLOAT_NUM', 'FLOAT'], true) => Builtin::DoublePrecision,
            in_array($name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true) => Builtin::Unknown,
            in_array($name, ['NULL_P', 'NULL_SYM', 'NULL'], true) => Builtin::Unknown,
            in_array($text, ['TRUE', 'FALSE'], true) && !in_array($name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) => Builtin::Boolean,
            default => null,
        };

        return $type === null ? null : new TypeDescriptor($this->dialect, $type);
    }

    /**
     * Chooses a PostgreSql integer width from its decimal spelling.
     */
    public function integer(string $text): Builtin
    {
        $digits = ltrim($text, '0');
        if (strlen($digits) < 10 || strlen($digits) === 10 && strcmp($digits, '2147483647') <= 0) {
            return Builtin::Integer;
        }
        return strlen($digits) < 19 || strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0 ? Builtin::BigInt : Builtin::Numeric;
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
            return new TypeDescriptor($this->dialect, Builtin::Text);
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
        return new TypeDescriptor($this->dialect, Builtin::Boolean);
    }

    /**
     * Resolves supported numeric operations, including signed literal boundaries.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(Operator $operator, array $operands, Node $source): TypeDescriptor
    {
        $type = (new TypeResolution($this->dialect))->common($operands, $source);
        if ($operator === Operator::Minus && count($operands) === 1 && $operands[0]->kind === ExpressionKind::Literal) {
            $magnitude = str_replace('_', '', $operands[0]->symbol ?? '');
            $type = match ($magnitude) {
                '2147483648' => new TypeDescriptor($this->dialect, Builtin::Integer),
                '9223372036854775808' => new TypeDescriptor($this->dialect, Builtin::BigInt),
                default => $type,
            };
        }
        if (!in_array($type->name, self::INTEGERS, true)) {
            Tree::unsupported($source, 'arithmetic type');
        }
        return $type;
    }

    /**
     * @throws SemanticException
     */
    public function predicate(Expression $expression): void
    {
        if (!$expression->type->is(Builtin::Boolean) && !$expression->type->is(Builtin::Unknown)) {
            throw new SemanticException('non-boolean-predicate', 'A PostgreSql predicate must have boolean type.', $expression->source);
        }
    }

    /**
     * @param list<Expression> $operands
     * @return list<Expression>
     */
    public function coalesce(array $operands, TypeDescriptor $type): array
    {
        return array_map(fn (Expression $operand): Expression => (new ExpressionRules($this->dialect))->coerce($operand, $type), $operands);
    }

    /**
     * Resolves the output type of a projected literal.
     */
    public function project(Expression $expression): Expression
    {
        if ($expression->kind === ExpressionKind::Literal && $expression->type->is(Builtin::Unknown)) {
            return (new ExpressionRules($this->dialect))->coerce($expression, new TypeDescriptor($this->dialect, Builtin::Text));
        }
        return $expression;
    }
}
