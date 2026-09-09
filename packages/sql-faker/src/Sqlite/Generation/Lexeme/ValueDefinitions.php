<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ChoiceDomain;
use SqlFaker\Grammar\Generation\Value\IntegerDomain;
use SqlFaker\Grammar\Generation\Value\SequenceDomain;

/**
 * Value domains from SQLite version-3.47.2 src/tokenize.c sqlite3GetToken.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/parse.y
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/tokenize.c
 */
final class ValueDefinitions
{
    /**
     * Composes value scanner cases while keeping each delimited value indivisible.
     */
    public function create(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator($this->names(), $this->numbers(), $this->strings());
    }

    /**
     * Implements identifier and bind-parameter character classes, including quoted names.
     */
    public function names(): LexemeGenerator
    {
        $names = [];
        foreach (['ID', 'id', 'idj', 'ANY'] as $terminal) {
            $names[] = new PatternLexemeGenerator($terminal, '/\A(?:[A-Za-z_][A-Za-z0-9_$]*|"(?:[^"\x00]|"")+"|`(?:[^`\x00]|``)+`|\[[^\]\x00]+\])\z/D', ['name'], 'identifier', 'src/tokenize.c:CC_ID/CC_QUOTE', new CharacterDomain(['a', 'Z', '0', ' ', '""', 'é', '猫'], 1, 64, '"', '"'));
        }
        $names[] = new PatternLexemeGenerator('VARIABLE', '/\A(?:\?[0-9]*|[:@$][A-Za-z_][A-Za-z0-9_$]*)\z/D', ['?1', ':value', '@value', '$value'], 'parameter', 'src/tokenize.c:CC_VARNUM/CC_VARALPHA', new ChoiceDomain(new SequenceDomain(new CharacterDomain(['?'], 1, 1), new IntegerDomain('1', '32766', 0)), new CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyz0123456789_$'), 0, 63, ':v')));
        return new ChoiceLexemeGenerator(...$names);
    }

    /**
     * Implements digit, decimal, exponent and digit-separator scanner forms.
     */
    public function numbers(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('INTEGER', '/\A(?:[0-9]+|0[xX][0-9a-fA-F]+)\z/D', ['1', '0', '2'], 'number', 'src/tokenize.c:CC_DIGIT', new IntegerDomain('0', '9223372036854775807')),
            new PatternLexemeGenerator('number', '/\A[0-9]+\z/D', ['1'], 'number', 'src/parse.y:number', new IntegerDomain('0', '9223372036854775807')),
            new PatternLexemeGenerator('FLOAT', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', ['1.5', '.5', '1e2'], 'number', 'src/tokenize.c:CC_DIGIT:float', new ChoiceDomain(new SequenceDomain(new IntegerDomain('0', '18446744073709551615'), new CharacterDomain(['.'], 1, 1), new CharacterDomain(str_split('0123456789'), 0, 30)), new SequenceDomain(new SequenceDomain(new IntegerDomain('0', '18446744073709551615'), new CharacterDomain(['.'], 1, 1), new CharacterDomain(str_split('0123456789'), 0, 30)), new CharacterDomain(['e+', 'E-'], 1, 1), new IntegerDomain('0', '308')))),
            new PatternLexemeGenerator('QNUMBER', '/\A[0-9]+(?:_[0-9]+)+\z/D', ['1_0'], 'number', 'src/tokenize.c:TK_QNUMBER', new SequenceDomain(new IntegerDomain('0', '2147483647'), new CharacterDomain(['_'], 1, 1), new IntegerDomain('0', '2147483647'))),
        );
    }

    /**
     * Implements doubled-quote strings and the even-hex-digit blob rule.
     */
    public function strings(): LexemeGenerator
    {
        $atoms = array_map(static fn (int $byte): string => $byte === 39 ? "''" : chr($byte), range(1, 127));
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('STRING', "/\A'(?:[^'\\x00]|'')*'\z/Ds", ["'text'", "'a''b'"], 'string', 'src/tokenize.c:CC_QUOTE', new CharacterDomain([...$atoms, 'é', '猫', '😀'], 0, 255, "'", "'")),
            new PatternLexemeGenerator('ids', "/\A'(?:[^'\\x00]|'')*'\z/Ds", ["'text'"], 'string', 'src/parse.y:ids', new CharacterDomain([...$atoms, 'é', '猫', '😀'], 0, 255, "'", "'")),
            new PatternLexemeGenerator('BLOB', "/\A[xX]'(?:[0-9a-fA-F]{2})*'\z/D", ["X'00'", "X''"], 'string', 'src/tokenize.c:CC_X', new CharacterDomain(str_split('0123456789abcdefABCDEF'), 0, 127, "X'", "'", 2)),
        );
    }
}
