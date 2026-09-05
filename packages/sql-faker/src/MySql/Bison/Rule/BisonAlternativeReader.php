<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Rule;

use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;

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
            if ($lexeme === BisonTokenType::Semicolon) {
                $stream->next();
                $alternatives[] = $draft->complete();

                return $alternatives;
            }

            if ($lexeme === BisonTokenType::Pipe) {
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

        if ($lexeme === BisonTokenType::Eof || $lexeme === BisonTokenType::PercentPercent) {
            return true;
        }

        return $lexeme === BisonTokenType::Identifier && $stream->peekN(2)->type === BisonTokenType::Colon;
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
            BisonTokenType::Action => $draft->setAction($stream->nextString()),
            BisonTokenType::Identifier => $draft->addSymbol(
                new BisonSymbolNode(BisonSymbolType::Identifier, $stream->nextString()),
            ),
            BisonTokenType::CharLiteral => $draft->addSymbol(
                new BisonSymbolNode(BisonSymbolType::CharLiteral, $stream->nextString()),
            ),
            BisonTokenType::Directive => $this->readInlineDirective($stream, $draft),
            BisonTokenType::Number,
            BisonTokenType::StringLiteral,
            BisonTokenType::TypeTag,
            BisonTokenType::Colon,
            BisonTokenType::Semicolon,
            BisonTokenType::Pipe,
            BisonTokenType::PercentPercent,
            BisonTokenType::Prologue,
            BisonTokenType::Eof => $stream->next(),
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
        return $stream->nextIf(BisonTokenType::Identifier, BisonTokenType::CharLiteral)?->asString();
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
        return $stream->nextIf(BisonTokenType::Number)?->asInt();
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
        return $stream->nextIf(BisonTokenType::TypeTag)?->asString();
    }
}
