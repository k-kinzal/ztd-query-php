<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Rule;

use SqlFaker\Compiler\Bison\Ast\BisonAlternativeNode;
use SqlFaker\Compiler\Bison\Ast\BisonSymbolForm;
use SqlFaker\Compiler\Bison\Ast\BisonSymbolNode;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;

/**
 * Reads the alternatives on the right-hand side of one rule.
 *
 * Bison lets the final semicolon be left out, so an alternative list also ends
 * where the next rule begins — an identifier followed by a colon — or at the
 * end of the section. All three endings complete the alternative in hand, which
 * is why the reader looks two tokens ahead before it consumes anything.
 *
 * @visibility root
 */
final class BisonAlternativeReader
{
    /**
     * Reads every alternative of the current rule.
     *
     * @param BisonTokenStream $stream Stream positioned on the first symbol after the colon
     *
     * @return list<BisonAlternativeNode> The alternatives, always at least one
     */
    public function readAll(BisonTokenStream $stream): array
    {
        $draft = new BisonAlternativeDraft();
        $alternatives = [];

        while (true) {
            if ($this->endsTheRule($stream)) {
                $alternatives[] = $draft->complete();

                return $alternatives;
            }

            $lexeme = $stream->peek()->type;
            if ($lexeme === BisonLexeme::Semicolon) {
                $stream->next();
                $alternatives[] = $draft->complete();

                return $alternatives;
            }

            if ($lexeme === BisonLexeme::Pipe) {
                $stream->next();
                $alternatives[] = $draft->complete();
                continue;
            }

            $this->readPart($stream, $draft);
        }
    }

    /**
     * Reports whether the alternative list is over without consuming anything.
     *
     * @param BisonTokenStream $stream Stream to look ahead in
     *
     * @return bool True at the end of the section or at the start of the next rule
     */
    public function endsTheRule(BisonTokenStream $stream): bool
    {
        $lexeme = $stream->peek()->type;

        if ($lexeme === BisonLexeme::Eof || $lexeme === BisonLexeme::PercentPercent) {
            return true;
        }

        return $lexeme === BisonLexeme::Identifier && $stream->peekN(2)->type === BisonLexeme::Colon;
    }

    /**
     * Reads one symbol, action or inline directive into the alternative.
     *
     * @param BisonTokenStream $stream Stream positioned on the part to read
     * @param BisonAlternativeDraft $draft Alternative being assembled
     */
    public function readPart(BisonTokenStream $stream, BisonAlternativeDraft $draft): void
    {
        match ($stream->peek()->type) {
            BisonLexeme::Action => $draft->setAction($stream->nextString()),
            BisonLexeme::Identifier => $draft->addSymbol(
                new BisonSymbolNode(BisonSymbolForm::Identifier, $stream->nextString()),
            ),
            BisonLexeme::CharLiteral => $draft->addSymbol(
                new BisonSymbolNode(BisonSymbolForm::CharLiteral, $stream->nextString()),
            ),
            BisonLexeme::Directive => $this->readInlineDirective($stream, $draft),
            BisonLexeme::Number,
            BisonLexeme::StringLiteral,
            BisonLexeme::TypeTag,
            BisonLexeme::Colon,
            BisonLexeme::Semicolon,
            BisonLexeme::Pipe,
            BisonLexeme::PercentPercent,
            BisonLexeme::Prologue,
            BisonLexeme::Eof => $stream->next(),
        };
    }

    /**
     * Applies `%prec`, `%dprec` or `%merge` to the alternative being read.
     *
     * @param BisonTokenStream $stream Stream positioned on the directive
     * @param BisonAlternativeDraft $draft Alternative being assembled
     */
    public function readInlineDirective(BisonTokenStream $stream, BisonAlternativeDraft $draft): void
    {
        match ($stream->nextString()) {
            '%prec' => $draft->setPrecedenceSymbol($this->readPrecedenceSymbol($stream)),
            '%dprec' => $draft->setDynamicPrecedence($this->readDynamicPrecedence($stream)),
            '%merge' => $draft->setMergeFunction($this->readMergeFunction($stream)),
            default => null,
        };
    }

    /**
     * Reads the terminal whose precedence `%prec` borrows.
     *
     * @param BisonTokenStream $stream Stream positioned just after `%prec`
     *
     * @return string|null The terminal, or null when the directive named none
     */
    public function readPrecedenceSymbol(BisonTokenStream $stream): ?string
    {
        return $stream->nextIf(BisonLexeme::Identifier, BisonLexeme::CharLiteral)?->asString();
    }

    /**
     * Reads the rank `%dprec` assigns to this alternative.
     *
     * @param BisonTokenStream $stream Stream positioned just after `%dprec`
     *
     * @return int|null The rank, or null when the directive named none
     */
    public function readDynamicPrecedence(BisonTokenStream $stream): ?int
    {
        return $stream->nextIf(BisonLexeme::Number)?->asInt();
    }

    /**
     * Reads the merge function `%merge` names for this alternative.
     *
     * @param BisonTokenStream $stream Stream positioned just after `%merge`
     *
     * @return string|null The function name, or null when the directive named none
     */
    public function readMergeFunction(BisonTokenStream $stream): ?string
    {
        return $stream->nextIf(BisonLexeme::TypeTag)?->asString();
    }
}
