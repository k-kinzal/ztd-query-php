<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;

/**
 * Value domains from SQLite version-3.47.2 src/tokenize.c sqlite3GetToken.
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
            $names[] = new PatternLexemeGenerator($terminal, '/\A(?:[A-Za-z_][A-Za-z0-9_$]*|"(?:[^"\x00]|"")+"|`(?:[^`\x00]|``)+`|\[[^\]\x00]+\])\z/D', ['name'], 'identifier', 'src/tokenize.c:CC_ID/CC_QUOTE');
        }
        $names[] = new PatternLexemeGenerator('VARIABLE', '/\A(?:\?[0-9]*|[:@$][A-Za-z_][A-Za-z0-9_$]*)\z/D', ['?1', ':value', '@value', '$value'], 'parameter', 'src/tokenize.c:CC_VARNUM/CC_VARALPHA');
        return new ChoiceLexemeGenerator(...$names);
    }

    /**
     * Implements digit, decimal, exponent and digit-separator scanner forms.
     */
    public function numbers(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('INTEGER', '/\A(?:[0-9]+|0[xX][0-9a-fA-F]+)\z/D', ['1', '0', '2'], 'number', 'src/tokenize.c:CC_DIGIT'),
            new PatternLexemeGenerator('number', '/\A[0-9]+\z/D', ['1'], 'number', 'src/parse.y:number'),
            new PatternLexemeGenerator('FLOAT', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', ['1.5', '.5', '1e2'], 'number', 'src/tokenize.c:CC_DIGIT:float'),
            new PatternLexemeGenerator('QNUMBER', '/\A[0-9]+(?:_[0-9]+)+\z/D', ['1_0'], 'number', 'src/tokenize.c:TK_QNUMBER'),
        );
    }

    /**
     * Implements doubled-quote strings and the even-hex-digit blob rule.
     */
    public function strings(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('STRING', "/\A'(?:[^'\\x00]|'')*'\z/Ds", ["'text'", "'a''b'"], 'string', 'src/tokenize.c:CC_QUOTE'),
            new PatternLexemeGenerator('ids', "/\A'(?:[^'\\x00]|'')*'\z/Ds", ["'text'"], 'string', 'src/parse.y:ids'),
            new PatternLexemeGenerator('BLOB', "/\A[xX]'(?:[0-9a-fA-F]{2})*'\z/D", ["X'00'", "X''"], 'string', 'src/tokenize.c:CC_X'),
        );
    }
}
