<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use SqlFaker\MySql\Bison\Lexer\BisonTokenType;

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
     * @param BisonTokenType $lexeme Lexeme waiting at the front of the stream
     *
     * @return bool True while the declaration continues, false where it ends
     */
    public function continuesWith(BisonTokenType $lexeme): bool
    {
        return match ($lexeme) {
            BisonTokenType::Directive,
            BisonTokenType::Prologue,
            BisonTokenType::PercentPercent,
            BisonTokenType::Eof => false,
            BisonTokenType::Identifier,
            BisonTokenType::Number,
            BisonTokenType::CharLiteral,
            BisonTokenType::StringLiteral,
            BisonTokenType::TypeTag,
            BisonTokenType::Colon,
            BisonTokenType::Semicolon,
            BisonTokenType::Pipe,
            BisonTokenType::Action => true,
        };
    }
}
