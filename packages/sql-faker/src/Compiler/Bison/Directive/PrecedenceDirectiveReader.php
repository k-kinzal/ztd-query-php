<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Directive;

use Override;
use SqlFaker\Compiler\Bison\Ast\BisonDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonPrecedenceDeclaration;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;

/**
 * Reads the four directives that rank operators against each other.
 *
 * `%left`, `%right`, `%nonassoc` and `%precedence` differ only in the
 * associativity they declare, and the directive name carries that, so one
 * reader covers all four. Operators may be written as names or as quoted
 * characters, and both spellings name the same terminal.
 *
 * @visibility root
 */
final class PrecedenceDirectiveReader implements BisonDirectiveReader
{
    /** @readonly */
    private BisonDeclarationBoundary $boundary;

    /**
     * @param BisonDeclarationBoundary|null $boundary Decides where the declaration ends
     */
    public function __construct(?BisonDeclarationBoundary $boundary = null)
    {
        $this->boundary = $boundary ?? new BisonDeclarationBoundary();
    }

    /**
     * Reports whether the directive declares an associativity.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%left", "%right", "%nonassoc" and "%precedence"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return match ($directive) {
            '%left', '%right', '%nonassoc', '%precedence' => true,
            default => false,
        };
    }

    /**
     * Consumes the optional type tag and the operators being ranked.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration The declaration, with no operators when none followed
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): BisonDeclaration
    {
        /** @var 'left'|'right'|'nonassoc'|'precedence' $associativity */
        $associativity = substr($directive, 1);

        $typeTag = $stream->nextIf(BisonLexeme::TypeTag)?->asString();

        /** @var list<string> $symbols */
        $symbols = [];
        while ($this->boundary->continuesWith($stream->peek()->type)) {
            $symbol = $stream->nextIf(BisonLexeme::Identifier, BisonLexeme::CharLiteral)?->asString();
            if ($symbol === null) {
                $stream->next();
                continue;
            }

            $symbols[] = $symbol;
        }

        return new BisonPrecedenceDeclaration($associativity, $typeTag, $symbols);
    }
}
