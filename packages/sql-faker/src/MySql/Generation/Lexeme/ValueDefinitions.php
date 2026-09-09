<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;

/**
 * Lexical domains from sql/sql_lex.cc: identifiers, get_text, int_token and numeric scanner states.
 * Representative values are defaults, not the accepted domain for an explicit Plan.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
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
            new PatternLexemeGenerator('IDENT', '/\A[A-Za-z_$][A-Za-z0-9_$]*\z/D', ['_sqlfaker_identifier'], 'identifier', 'sql/sql_lex.cc:MY_LEX_IDENT', new CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$'), 0, 60, '_sf')),
            new PatternLexemeGenerator('IDENT_QUOTED', '/\A`(?:[^`\x00]|``)+`\z/D', ['`name`'], 'quoted-identifier', 'sql/sql_lex.cc:MY_LEX_USER_VARIABLE_DELIMITER', new CharacterDomain(['a', 'Z', '0', ' ', '``', 'é', '猫'], 1, 64, '`', '`')),
            new PatternLexemeGenerator('LEX_HOSTNAME', '/\A[A-Za-z0-9_.$]+\z/D', ['localhost'], 'hostname', 'sql/sql_lex.cc:MY_LEX_HOSTNAME', new CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyz0123456789_.$'), 1, 64)),
            new PatternLexemeGenerator('UNDERSCORE_CHARSET', '/\A_(?:utf8mb4|utf8mb3|latin1|ascii|binary)\z/Di', ['_utf8mb4'], 'charset', 'sql/sql_lex.cc:MY_LEX_IDENT:charset'),
        );
    }

    /**
     * Implements quoted text, doubled quotes and backslash escapes in the default SQL mode.
     */
    public function strings(): LexemeGenerator
    {
        $atoms = array_map(static fn (int $byte): string => match ($byte) {
            39 => "''", 92 => '\\\\', default => chr($byte)
        }, range(1, 127));
        $text = "'(?:[^'\\\\\\x00]|''|\\\\.)*'";
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('TEXT_STRING', '~\A' . $text . '\z~Ds', ["'text'", "'a''b'"], 'string', 'sql/sql_lex.cc:get_text', new CharacterDomain([...$atoms, 'é', '猫'], 0, 255, "'", "'")),
            new PatternLexemeGenerator('NCHAR_STRING', '~\AN' . $text . '\z~Dis', ["N'text'"], 'string', 'sql/sql_lex.cc:MY_LEX_IDENT_OR_NCHAR', new CharacterDomain([...$atoms, 'é', '猫'], 0, 255, "N'", "'")),
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
            new PatternLexemeGenerator('DECIMAL_NUM', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)\z/D', ['1.5'], 'number', 'sql/sql_lex.cc:MY_LEX_REAL', new SequenceDomain(new IntegerDomain('0', '18446744073709551615'), new CharacterDomain(['.'], 1, 1), new CharacterDomain(str_split('0123456789'), 0, 30))),
            new PatternLexemeGenerator('FLOAT_NUM', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)[eE][+-]?[0-9]+\z/D', ['1e2'], 'number', 'sql/sql_lex.cc:MY_LEX_REAL:exponent', new SequenceDomain(new SequenceDomain(new IntegerDomain('0', '18446744073709551615'), new CharacterDomain(['.'], 1, 1), new CharacterDomain(str_split('0123456789'), 0, 30)), new CharacterDomain(['e+', 'E-'], 1, 1), new IntegerDomain('0', '308'))),
        );
    }

    /**
     * Implements prefix and quoted binary forms, including the even-digit rule for quoted hex.
     */
    public function binary(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('HEX_NUM', "/\A(?:0x[0-9a-fA-F]+|[xX]'(?:[0-9a-fA-F]{2})*')\z/D", [sprintf('0x%02x', 15), "X'0f'"], 'number', 'sql/sql_lex.cc:MY_LEX_HEX_NUMBER', new ChoiceDomain(new CharacterDomain(str_split('0123456789abcdefABCDEF'), 1, 32, '0x'), new CharacterDomain(str_split('0123456789abcdefABCDEF'), 0, 16, "X'", "'", 2))),
            new PatternLexemeGenerator('BIN_NUM', "/\A(?:0b[01]+|[bB]'[01]*')\z/D", ['0b01', "B'01'"], 'number', 'sql/sql_lex.cc:MY_LEX_BIN_NUMBER', new ChoiceDomain(new CharacterDomain(['0', '1'], 1, 64, '0b'), new CharacterDomain(['0', '1'], 0, 64, "B'", "'"))),
        );
    }
}
