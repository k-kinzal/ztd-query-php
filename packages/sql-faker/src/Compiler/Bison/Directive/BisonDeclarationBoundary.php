<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Directive;

use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;

/**
 * Decides where one declaration stops and the next thing starts.
 *
 * Bison declarations are not terminated: `%token A B C` runs until something
 * that can only begin something else appears. Every reader that consumes a list
 * of symbols needs that judgement, and it has to be the same judgement in all
 * of them, or two readers would disagree about which declaration a symbol
 * belongs to.
 *
 * @visibility root
 */
final class BisonDeclarationBoundary
{
    /**
     * Reports whether a lexeme still belongs to the declaration being read.
     *
     * @param BisonLexeme $lexeme Lexeme waiting at the front of the stream
     *
     * @return bool True while the declaration continues, false where it ends
     */
    public function continuesWith(BisonLexeme $lexeme): bool
    {
        return match ($lexeme) {
            BisonLexeme::Directive,
            BisonLexeme::Prologue,
            BisonLexeme::PercentPercent,
            BisonLexeme::Eof => false,
            BisonLexeme::Identifier,
            BisonLexeme::Number,
            BisonLexeme::CharLiteral,
            BisonLexeme::StringLiteral,
            BisonLexeme::TypeTag,
            BisonLexeme::Colon,
            BisonLexeme::Semicolon,
            BisonLexeme::Pipe,
            BisonLexeme::Action => true,
        };
    }
}
