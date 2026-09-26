<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Model\Operator;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\Type\Builtin;
use SqlSemantics\Core\Type\TypeDeclaration;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Sqlite TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    private const SUPPORTED = [
        Builtin::Unknown,
        Builtin::Dynamic,
        Builtin::Any,
        Builtin::TinyInt,
        Builtin::SmallInt,
        Builtin::MediumInt,
        Builtin::Integer,
        Builtin::BigInt,
        Builtin::Numeric,
        Builtin::Real,
        Builtin::DoublePrecision,
        Builtin::Boolean,
        Builtin::Char,
        Builtin::VarChar,
        Builtin::Text,
        Builtin::Blob,
        Builtin::Date,
        Builtin::DateTime,
    ];

    /**
     * Retains the language identity used in semantic output.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Reads a declared type name, its affinity, and the table options that change it.
     */
    public function read(Node $node, ValueReader $values, ?Node $table = null): TypeDeclaration
    {
        return (new TypeReader($this->dialect))->read($node, $table);
    }

    /**
     * Reports whether this dialect has the built-in type.
     */
    public function supports(Builtin $type): bool
    {
        return in_array($type, self::SUPPORTED, true);
    }

    /**
     * Types a literal terminal without converting its contents.
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
            in_array($name, ['FLOAT_NUM', 'FLOAT'], true) => Builtin::Real,
            in_array($name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true) => Builtin::Text,
            in_array($name, ['NULL_P', 'NULL_SYM', 'NULL'], true) => Builtin::Unknown,
            in_array($text, ['TRUE', 'FALSE'], true) && !in_array($name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) => Builtin::Integer,
            default => null,
        };

        return $type === null ? null : new TypeDescriptor($this->dialect, $type);
    }

    /**
     * Chooses a Sqlite integer width from its decimal spelling; an overflowing literal is a real.
     */
    public function integer(string $text): Builtin
    {
        $digits = ltrim($text, '0');
        return strlen($digits) < 19 || strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0 ? Builtin::Integer : Builtin::Real;
    }

    /**
     * @param list<Expression> $expressions
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
        foreach ($types as $type) {
            if (!$type->is($types[0]->name)) {
                return new TypeDescriptor($this->dialect, Builtin::Dynamic);
            }
        }

        return new TypeDescriptor($this->dialect, $types[0]->name, affinity: $types[0]->affinity);
    }

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor
    {
        return new TypeDescriptor($this->dialect, Builtin::Integer);
    }

    /**
     * Arithmetic results take the storage class of their run-time operands.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(Operator $operator, array $operands, Node $source): TypeDescriptor
    {
        return new TypeDescriptor($this->dialect, Builtin::Dynamic);
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
