<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;

/**
 * Value domains from REL_17_2 src/backend/parser/scan.l, under standard_conforming_strings.
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
            new PatternLexemeGenerator('IDENT', '/\A(?:[A-Za-z_][A-Za-z0-9_$]*|"(?:[^"\x00]|"")+")\z/D', ['_sqlfaker_identifier'], 'identifier', 'scan.l:identifier/xd'),
            new PatternLexemeGenerator('UIDENT', '/\AU&"(?:[^"\x00]|"")+"\z/Di', ['U&"name"'], 'quoted-identifier', 'scan.l:xui'),
            new PatternLexemeGenerator('PARAM', '/\A\$[1-9][0-9]*\z/D', ['$1'], 'parameter', 'scan.l:param'),
        );
    }

    /**
     * Keeps string delimiters, escapes and binary prefixes inside a single lexeme.
     */
    public function strings(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('SCONST', "~\A(?:'(?:[^'\x00]|'')*'|[eE]'(?:[^'\\\\\x00]|''|\\\\.)*'|\$\$(?:(?!\$\$).)*\$\$)\z~Ds", ["'text'", "'a''b'", '$$text$$'], 'string', 'scan.l:xq/xe/xdolq'),
            new PatternLexemeGenerator('USCONST', "~\A[Uu]&'(?:[^'\x00]|'')*'\z~Ds", ["U&'text'"], 'string', 'scan.l:xus'),
            new PatternLexemeGenerator('BCONST', "/\A[bB]'[01]*'\z/D", ["B'01'"], 'string', 'scan.l:xb'),
            new PatternLexemeGenerator('XCONST', "/\A[xX]'[0-9a-fA-F]*'\z/D", ["X'0f'"], 'string', 'scan.l:xh'),
        );
    }

    /**
     * Implements numeric and operator forms with comment-opening sequences excluded.
     */
    public function numbers(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new PatternLexemeGenerator('ICONST', '/\A[0-9]+(?:_[0-9]+)*\z/D', ['1', '0', '2'], 'number', 'scan.l:decinteger'),
            new PatternLexemeGenerator('FCONST', '/\A(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\z/D', ['1.5', '.5', '1e2'], 'number', 'scan.l:numeric/real'),
            new PatternLexemeGenerator('Op', '~\A(?!.*(?:--|/\*))[+*/<>=!@#%^&|`?\x7e-]+\z~D', ['?', '?|', '?&'], 'operator', 'scan.l:operator'),
        );
    }
}
