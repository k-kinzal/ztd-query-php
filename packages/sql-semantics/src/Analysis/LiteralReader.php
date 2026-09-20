<?php

declare(strict_types=1);

namespace SqlSemantics\Analysis;

use SqlParser\Lexer\Token;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Interprets literal categories from lexer terminals, preserving their SQL spelling.
 *
 * @visibility SqlSemantics
 */
final class LiteralReader
{
    /**
     * Binds the dependencies used for this analysis.
     */
    public function __construct(public readonly Dialect $dialect)
    {
    }

    /**
     * Classifies a literal or parameter terminal without evaluating SQL.
     */
    public function read(Token $token): ?Expression
    {
        $name = $token->name;
        $text = strtoupper($token->text);
        if (in_array($name, ['PARAM', 'PARAM_MARKER', 'VARIABLE'], true)) {
            return new Expression(ExpressionKind::Parameter, new TypeDescriptor($this->dialect, 'unknown'), Nullability::Unknown, $token, symbol: $token->text);
        }
        $type = $this->typeName($token);
        if ($type === null) {
            return null;
        }

        return new Expression(ExpressionKind::Literal, new TypeDescriptor($this->dialect, $type), $text === 'NULL' ? Nullability::AlwaysNull : Nullability::NotNull, $token, symbol: $token->text);
    }

    /**
     * Classifies a literal's lexical category without converting its contents.
     */
    public function typeName(Token $token): ?string
    {
        $name = $token->name;
        $number = str_replace('_', '', $token->text);
        if (in_array($name, ['ICONST', 'FCONST', 'INTEGER', 'NUM', 'LONG_NUM', 'ULONGLONG_NUM'], true) && preg_match('/^0[xob]/i', $number) === 1) {
            \SqlSemantics\Ast\Tree::unsupported($token, 'non-decimal numeric literal');
        }
        $text = strtoupper($token->text);
        return match (true) {
            in_array($name, ['ICONST', 'NUM', 'INTEGER'], true) => $this->integer($number),
            $name === 'LONG_NUM' => 'bigint',
            $name === 'ULONGLONG_NUM' => 'bigint unsigned',
            $name === 'FCONST' => ctype_digit($number) ? $this->integer($number) : 'numeric',
            $name === 'DECIMAL_NUM' => 'numeric',
            in_array($name, ['FLOAT_NUM', 'FLOAT'], true) => $this->dialect === Dialect::Sqlite ? 'real' : 'double precision',
            in_array($name, ['SCONST', 'USCONST', 'TEXT_STRING', 'STRING'], true) => $this->dialect === Dialect::PostgreSql ? 'unknown' : 'text',
            in_array($name, ['NULL_P', 'NULL_SYM', 'NULL'], true) => 'unknown',
            in_array($text, ['TRUE', 'FALSE'], true) && !in_array($name, ['IDENT', 'IDENT_QUOTED', 'ID'], true) => $this->dialect === Dialect::PostgreSql ? 'boolean' : 'integer',
            default => null,
        };
    }

    /**
     * Chooses a PostgreSQL integer width from its decimal spelling.
     */
    public function integer(string $text): string
    {
        $digits = ltrim($text, '0');
        if ($this->dialect === Dialect::Sqlite) {
            return strlen($digits) < 19 || (strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0) ? 'integer' : 'real';
        }
        if ($this->dialect !== Dialect::PostgreSql || strlen($digits) < 10 || (strlen($digits) === 10 && strcmp($digits, '2147483647') <= 0)) {
            return 'integer';
        }

        return strlen($digits) < 19 || (strlen($digits) === 19 && strcmp($digits, '9223372036854775807') <= 0) ? 'bigint' : 'numeric';
    }
}
