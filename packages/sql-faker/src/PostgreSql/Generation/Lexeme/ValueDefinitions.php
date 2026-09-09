<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\DollarQuotedDomain;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;

/**
 * Value domains from REL_17_2 src/backend/parser/scan.l, under standard_conforming_strings.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/scan.l
 */
final class ValueDefinitions
{
    /**
     * Composes lexical domains without asking the independent PHP tokenizer to approve them.
     */
    public function create(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator($this->names(), $this->strings(), $this->numbers());
    }

    /**
     * Implements identifier, quoted identifier and parameter patterns from scan.l.
     */
    public function names(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('IDENT', '/\A(?:[A-Za-z_][A-Za-z0-9_$]*|"(?:[^"\x00]|"")+")\z/D', ['_sqlfaker_identifier'], 'identifier', 'scan.l:identifier/xd', new ChoiceDomain(new CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_$'), 0, 59, '_sf'), new CharacterDomain(['a', 'Z', '0', ' ', '""', 'é', '猫'], 1, 63, '"', '"'))),
            new PatternLexemeGenerator('UIDENT', '/\AU&"(?:[^"\x00]|"")+"\z/Di', ['U&"name"'], 'quoted-identifier', 'scan.l:xui', new CharacterDomain(['a', 'Z', '0', ' ', '""', 'é', '猫'], 1, 63, 'U&"', '"')),
            new PatternLexemeGenerator('PARAM', '/\A\$[1-9][0-9]*\z/D', ['$1'], 'parameter', 'scan.l:param', new SequenceDomain(new CharacterDomain(['$'], 1, 1), new IntegerDomain('1', '65535', 0))),
        );
    }

    /**
     * Keeps string delimiters, escapes and binary prefixes inside a single lexeme.
     */
    public function strings(): LexemeGenerator
    {
        $dollar = '(\$[A-Za-z_][A-Za-z0-9_]*\$|\$\$)(?:(?!\1)[^\x00])*\1';
        $plain = array_map(static fn (int $byte): string => $byte === 39 ? "''" : chr($byte), range(1, 127));
        $escaped = array_map(static fn (string $atom): string => $atom === '\\' ? '\\\\' : $atom, $plain);
        $dollarBody = array_map(chr(...), [...range(1, 35), ...range(37, 127)]);
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('JSON_TABLE_PATH', "/\\A'(?:[^'\\x00]|'')*'\\z/Ds", ["'$'", "'$[*]'"], 'string', 'gram.y:json_table:string-path'),
            new PatternLexemeGenerator('SCONST', "~\A(?:'(?:[^'\\x00]|'')*'|[eE]'(?:[^'\\\\\\x00]|''|\\\\.)*'|" . $dollar . ")\z~Ds", ["'text'", "'a''b'", '$$text$$'], 'string', 'scan.l:xq/xe/xdolq', new ChoiceDomain(new CharacterDomain([...$plain, 'é', '猫', '😀'], 0, 255, "'", "'"), new CharacterDomain([...$escaped, 'é', '猫', '😀'], 0, 255, "E'", "'"), new DollarQuotedDomain(new ChoiceDomain(new CharacterDomain(['a'], 0, 0), new CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_'), 0, 16, '_')), new CharacterDomain([...$dollarBody, 'é', '猫', '😀'], 0, 255)))),
            new PatternLexemeGenerator('USCONST', "~\A[Uu]&'(?:[^'\\x00]|'')*'\z~Ds", ["U&'text'"], 'string', 'scan.l:xus', new CharacterDomain([...$escaped, '\0061', 'é', '猫', '😀'], 0, 255, "U&'", "'")),
            new PatternLexemeGenerator('BCONST', "/\A[bB]'[01]*'\z/D", ["B'01'"], 'string', 'scan.l:xb', new CharacterDomain(['0', '1'], 0, 255, "B'", "'")),
            new PatternLexemeGenerator('XCONST', "/\A[xX]'[0-9a-fA-F]*'\z/D", ["X'0f'"], 'string', 'scan.l:xh', new CharacterDomain(str_split('0123456789abcdefABCDEF'), 0, 255, "X'", "'")),
        );
    }

    /**
     * Implements numeric and operator forms with comment-opening sequences excluded.
     */
    public function numbers(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new IntegerLexemeGenerator('ICONST', '0', '2147483647', ['1', '0', '2', '1_0'], 'scan.l:process_integer_literal:ICONST', true),
            new PatternLexemeGenerator('FCONST', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', ['1.5', '.5', '1e2'], 'number', 'scan.l:numeric/real', new ChoiceDomain(new SequenceDomain(new IntegerDomain('0', '18446744073709551615'), new CharacterDomain(['.'], 1, 1), new CharacterDomain(str_split('0123456789'), 0, 30)), new SequenceDomain(new SequenceDomain(new IntegerDomain('0', '18446744073709551615'), new CharacterDomain(['.'], 1, 1), new CharacterDomain(str_split('0123456789'), 0, 30)), new CharacterDomain(['e+', 'E-'], 1, 1), new IntegerDomain('0', '308')))),
            new PatternLexemeGenerator('Op', '~\A(?!.*(?:--|/\*))(?!(?:[+*/%^<>=-]|>=|<=|=>|<>|!=)\z)(?![+*/<>=-]+[+-]\z)[+*/<>=!@#%^&|`?\x7e-]{1,63}\z~D', ['?', '?|', '?&'], 'operator', 'scan.l:operator', new CharacterDomain(str_split('+*<>=!@#%^&|`?~'), 0, 31, '?')),
        );
    }
}
