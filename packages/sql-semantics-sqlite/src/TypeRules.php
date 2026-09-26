<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * Sqlite TypeRules implementation.
 *
 * @visibility SqlSemantics
 */
final class TypeRules implements Contract
{
    /**
     * Retains the language identity used in semantic output.
     */
    public function __construct(private readonly Dialect $dialect)
    {
    }

    /**
     * Reads the declared built-in type and preserves its modifiers.
     */
    public function read(Node $node): TypeDescriptor
    {
        $tokens = $node->tokens();
        $words = [];
        $modifiers = [];
        $inModifiers = false;
        foreach ($tokens as $token) {
            if ($token->text === '(') {
                $inModifiers = true;
            } elseif ($token->text === ')') {
                $inModifiers = false;
            } elseif ($token->text !== ',') {
                if ($inModifiers) {
                    $modifiers[] = $token->text;
                } else {
                    $words[] = strtoupper($token->text);
                }
            }
        }
        $name = implode(' ', $words);
        return new TypeDescriptor($this->dialect, strtolower($name), $modifiers, $this->affinity($name));
    }

    /**
     * Resolves the built-in aliases modeled for this dialect.
     */
    public function canonical(string $name): ?string
    {
        return match ($name) {
            'INT', 'INTEGER', 'INT4' => 'integer',
            'SMALLINT', 'INT2' => 'smallint',
            'BIGINT', 'INT8' => 'bigint',
            'DEC', 'DECIMAL', 'NUMERIC' => 'numeric',
            'REAL' => 'real',
            'FLOAT4' => 'real',
            'DOUBLE', 'DOUBLE PRECISION', 'FLOAT8' => 'double precision',
            'BOOL', 'BOOLEAN' => 'boolean',
            'VARCHAR', 'CHARACTER VARYING', 'CHAR VARYING' => 'varchar',
            'CHAR', 'CHARACTER' => 'char',
            'TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'JSON' => strtolower($name),
            'TINYINT', 'MEDIUMINT', 'DATETIME', 'BLOB' => null,
            'UUID', 'BYTEA', 'JSONB', 'TIMESTAMPTZ', 'TIMETZ', 'INTERVAL' => null,
            default => null,
        };
    }

    /**
     * Computes storage affinity from a declaration name.
     */
    public function affinity(string $name): string
    {
        if (str_contains($name, 'INT')) {
            return 'integer';
        }
        if (str_contains($name, 'CHAR') || str_contains($name, 'CLOB') || str_contains($name, 'TEXT')) {
            return 'text';
        }
        if ($name === '' || str_contains($name, 'BLOB')) {
            return 'blob';
        }
        if (str_contains($name, 'REAL') || str_contains($name, 'FLOA') || str_contains($name, 'DOUB')) {
            return 'real';
        }
        return 'numeric';
    }

    /**
     * Classifies a literal's lexical category without converting its contents.
     */
    public function typeName(Token $token): ?string
    {
        $name = $token->name;
        $number = str_replace('_', '', $token->text);
        if (in_array($name, ['ICONST', 'FCONST', 'INTEGER', 'NUM', 'LONG_NUM', 'ULONGLONG_NUM'], true) && preg_match('/^0[xob]/i', $number) === 1) {
            Tree::unsupported($token, 'non-decimal numeric literal');
        }
        $text = strtoupper($token->text);
        return match (true) {
            in_array($name, ['ICONST', 'NUM', 'INTEGER'], true) => $this->integer($number),
            $name === 'LONG_NUM' => 'bigint',
            $name === 'ULONGLONG_NUM' => 'bigint unsigned',
            $name === 'FCONST' => ctype_digit($number) ? $this->integer($number) : 'numeric',
            $name === 'DECIMAL_NUM' => 'numeric',
            in_array($name, ['FLOAT_NUM', 'FLOAT'], true) => 'real',
            in_array($name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true) => 'text',
            in_array($name, ['NULL_P', 'NULL_SYM', 'NULL'], true) => 'unknown',
            in_array($text, ['TRUE', 'FALSE'], true) && !in_array($name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) => 'integer',
            default => null,
        };
    }

    /**
     * Chooses a Sqlite integer width from its decimal spelling.
     */
    public function integer(string $text): string
    {
        $digits = ltrim($text, '0');
        return strlen($digits) < 19 || strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0 ? 'integer' : 'real';
    }

    /**
     * @param list<Expression> $expressions
     */
    public function common(array $expressions, Node|Token $source): TypeDescriptor
    {
        $types = [];
        foreach ($expressions as $expression) {
            if ($expression->type->name !== 'unknown') {
                $types[] = $expression->type;
            }
        }
        if ($types === []) {
            return new TypeDescriptor($this->dialect, 'unknown');
        }
        $names = array_values(array_unique(array_map(static fn (TypeDescriptor $type): string => $type->name, $types)));
        if (count($names) === 1) {
            return new TypeDescriptor($this->dialect, $types[0]->name, affinity: $types[0]->affinity);
        }
        return new TypeDescriptor($this->dialect, 'dynamic');
    }

    /**
     * Returns the dialect result type of a predicate.
     */
    public function boolean(): TypeDescriptor
    {
        return new TypeDescriptor($this->dialect, 'integer');
    }

    /**
     * Resolves supported numeric operations, including signed literal boundaries.
     *
     * @param non-empty-list<Expression> $operands
     */
    public function arithmetic(string $operator, array $operands, Node $source): TypeDescriptor
    {
        return new TypeDescriptor($this->dialect, 'dynamic');
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
