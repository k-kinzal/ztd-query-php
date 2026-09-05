<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Directive;

use SqlFaker\MySql\Bison\Ast\BisonDeclaration;
use SqlFaker\MySql\Bison\Lexer\BisonTokenStream;

/**
 * Routes a directive to the reader that knows its arguments.
 *
 * The order matters here in a way it does not for the lexer's scanners: the
 * reader for unknown directives accepts everything, so it has to be consulted
 * last. Keeping that in one place is what lets every other reader answer only
 * for itself.
 *
 * @visibility root
 */
final class BisonDirectiveReaderChain
{
    /**
     * @param list<BisonDirectiveReader> $readers Readers in the order they are consulted
     */
    public function __construct(private readonly array $readers)
    {
    }

    /**
     * Builds the chain covering the directives this parser models.
     *
     * @return self A chain ending in the reader that accepts anything left over
     */
    public static function forBisonGrammar(): self
    {
        $boundary = new BisonDeclarationBoundary();

        return new self([
            new StartDirectiveReader(),
            new TokenDirectiveReader($boundary),
            new TypeDirectiveReader($boundary),
            new PrecedenceDirectiveReader($boundary),
            new ParamDirectiveReader(),
            new ExpectDirectiveReader(),
            new DefineDirectiveReader(),
            new UnknownDirectiveReader($boundary),
        ]);
    }

    /**
     * Consumes one declaration's arguments with the reader that claims it.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no reader claimed it
     *                               or the arguments were not the expected shape
     */
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        foreach ($this->readers as $reader) {
            if ($reader->handles($directive)) {
                return $reader->read($stream, $directive);
            }
        }

        return null;
    }
}
