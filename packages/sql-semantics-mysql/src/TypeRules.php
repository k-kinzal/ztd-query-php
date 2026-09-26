<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Core\Binding\TypeResolution;
use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Model\Expression;
use SqlSemantics\Core\Policy\TypeRules as Contract;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Core\Type\TypeDescriptor;

/**
 * MySql TypeRules implementation.
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
        $canonical = $this->canonical($name);
        if ($canonical === null) {
            Tree::unsupported($node, 'type declaration');
        }
        return new TypeDescriptor($this->dialect, $canonical, $modifiers);
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
            'REAL' => 'double precision',
            'FLOAT4' => 'real',
            'DOUBLE', 'DOUBLE PRECISION', 'FLOAT8' => 'double precision',
            'BOOL', 'BOOLEAN' => 'tinyint',
            'VARCHAR', 'CHARACTER VARYING', 'CHAR VARYING' => 'varchar',
            'CHAR', 'CHARACTER' => 'char',
            'TEXT', 'DATE', 'TIME', 'TIMESTAMP', 'JSON' => strtolower($name),
            'TINYINT', 'MEDIUMINT', 'DATETIME', 'BLOB' => strtolower($name),
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
            in_array($name, ['FLOAT_NUM', 'FLOAT'], true) => 'double precision',
            in_array($name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true) => 'text',
            in_array($name, ['NULL_P', 'NULL_SYM', 'NULL'], true) => 'unknown',
            in_array($text, ['TRUE', 'FALSE'], true) && !in_array($name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) => 'integer',
            default => null,
        };
    }

    /**
     * Chooses a MySql integer width from its decimal spelling.
     */
    public function integer(string $text): string
    {
        $digits = ltrim($text, '0');
        return 'integer';
    }

    /**
     * @param list<Expression> $expressions
     * @throws SemanticException
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
        $numeric = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision'];
        if (array_diff($names, $numeric) === []) {
            $rank = 0;
            foreach ($numeric as $index => $name) {
                if (in_array($name, $names, true)) {
                    $rank = $index;
                }
            }
            return new TypeDescriptor($this->dialect, $numeric[$rank]);
        }
        if (array_diff($names, ['varchar', 'text', 'char']) === []) {
            return new TypeDescriptor($this->dialect, 'text');
        }
        throw new SemanticException('unsupported-coercion', 'Cannot establish a common type for: ' . implode(', ', $names), $source);
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
        $type = (new TypeResolution($this->dialect))->common($operands, $source);
        if (!in_array(strtolower($type->name), ['smallint', 'integer', 'bigint'], true)) {
            Tree::unsupported($source, 'arithmetic type');
        }
        $type = new TypeDescriptor($this->dialect, 'bigint');
        return $type;
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
