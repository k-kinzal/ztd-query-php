<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Directive;

use Override;
use SqlFaker\Compiler\Bison\Ast\BisonDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonUnknownDeclaration;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;

/**
 * Reads any directive no other reader knows.
 *
 * Bison keeps adding directives and MySQL's grammar uses several this parser
 * has no model for. Skipping them would silently drop content and leave the
 * stream pointing into the middle of a declaration, so the arguments are
 * consumed to the boundary and kept as text: the declaration survives the round
 * trip even though nothing understands it.
 *
 * @visibility root
 */
final class UnknownDirectiveReader implements BisonDirectiveReader
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
     * Reports that this reader accepts every directive.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool Always true, which is why this reader is consulted last
     */
    #[Override]
    public function handles(string $directive): bool
    {
        unset($directive);

        return true;
    }

    /**
     * Consumes the arguments as text up to the end of the declaration.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration The directive and the text that followed it
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): BisonDeclaration
    {
        $parts = [];
        while ($this->boundary->continuesWith($stream->peek()->type)) {
            $parts[] = $stream->nextString();
        }

        return new BisonUnknownDeclaration($directive, implode(' ', $parts));
    }
}
