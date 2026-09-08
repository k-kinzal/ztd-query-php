<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;

/**
 * Lexical domains from sql/sql_lex.cc: identifiers, get_text, int_token and numeric scanner states.
 * Representative values are defaults, not the accepted domain for an explicit Plan.
 */
final class ValueDefinitions
{
    /**
     * Combines the value-producing scanner states, with quoting kept inside each lexeme.
     */
    public function create(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator($this->names(), $this->strings(), $this->numbers(), $this->binary());
    }

    /**
     * Implements ASCII identifier and hostname runs and doubled backtick delimiters.
     */
    public function names(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('IDENT', '/\A[A-Za-z_$][A-Za-z0-9_$]*\z/D', ['_sqlfaker_identifier'], 'identifier', 'sql/sql_lex.cc:MY_LEX_IDENT'),
            new PatternLexemeGenerator('IDENT_QUOTED', '/\A`(?:[^`\x00]|``)+`\z/D', ['`name`'], 'quoted-identifier', 'sql/sql_lex.cc:MY_LEX_USER_VARIABLE_DELIMITER'),
            new PatternLexemeGenerator('LEX_HOSTNAME', '/\A[A-Za-z0-9_.$]+\z/D', ['localhost'], 'hostname', 'sql/sql_lex.cc:MY_LEX_HOSTNAME'),
            new PatternLexemeGenerator('UNDERSCORE_CHARSET', '/\A_(?:utf8mb4|utf8mb3|latin1|ascii|binary)\z/Di', ['_utf8mb4'], 'charset', 'sql/sql_lex.cc:MY_LEX_IDENT:charset'),
        );
    }

    /**
     * Implements quoted text, doubled quotes and backslash escapes in the default SQL mode.
     */
    public function strings(): LexemeGenerator
    {
        $text = "'(?:[^'\\\\\\x00]|''|\\\\.)*'";
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('TEXT_STRING', '~\A' . $text . '\z~Ds', ["'text'", "'a''b'"], 'string', 'sql/sql_lex.cc:get_text'),
            new PatternLexemeGenerator('NCHAR_STRING', '~\AN' . $text . '\z~Dis', ["N'text'"], 'string', 'sql/sql_lex.cc:MY_LEX_IDENT_OR_NCHAR'),
        );
    }

    /**
     * Keeps integer widths explicit and decimal/exponent characters inside a single lexeme.
     */
    public function numbers(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new IntegerLexemeGenerator('NUM', '0', '2147483647', ['1', '0', '2'], 'sql/sql_lex.cc:int_token:NUM'),
            new IntegerLexemeGenerator('LONG_NUM', '2147483648', '9223372036854775807', ['2147483648'], 'sql/sql_lex.cc:int_token:LONG_NUM'),
            new IntegerLexemeGenerator('ULONGLONG_NUM', '9223372036854775808', '18446744073709551615', ['18446744073709551615'], 'sql/sql_lex.cc:int_token:ULONGLONG_NUM'),
            new PatternLexemeGenerator('DECIMAL_NUM', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)\z/D', ['1.5'], 'number', 'sql/sql_lex.cc:MY_LEX_REAL'),
            new PatternLexemeGenerator('FLOAT_NUM', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)[eE][+-]?[0-9]+\z/D', ['1e2'], 'number', 'sql/sql_lex.cc:MY_LEX_REAL:exponent'),
        );
    }

    /**
     * Implements prefix and quoted binary forms, including the even-digit rule for quoted hex.
     */
    public function binary(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('HEX_NUM', "/\A(?:0x[0-9a-fA-F]+|[xX]'(?:[0-9a-fA-F]{2})*')\z/D", [sprintf('0x%02x', 15), "X'0f'"], 'number', 'sql/sql_lex.cc:MY_LEX_HEX_NUMBER'),
            new PatternLexemeGenerator('BIN_NUM', "/\A(?:0b[01]+|[bB]'[01]*')\z/D", ['0b01', "B'01'"], 'number', 'sql/sql_lex.cc:MY_LEX_BIN_NUMBER'),
        );
    }
}
