<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;

/**
 * A lossless SQL parser configured for a grammar release and lexical settings.
 *
 * Consumers depend on this contract instead of enumerating concrete parsers.
 *
 * @visibility public
 * @example Declare a consumer of the parsing contract
 *     $roundTrip = static fn (\SqlParser\Parser\SqlParser $parser, string $sql): string => $parser->parse($sql)->toString();
 *     $roundTrip instanceof \Closure // => true
 */
interface SqlParser
{
    /**
     * Returns the configured grammar release tag.
     */
    public function version(): string;

    /**
     * Reads terminals, including their leading trivia and the end marker.
     *
     * @return list<Token>
     * @throws SourceException When the text cannot be tokenized
     */
    public function tokenize(string $sql): array;

    /**
     * Reads a concrete syntax tree that preserves every byte of the input.
     *
     * @throws SourceException When the text is not accepted by the grammar
     */
    public function parse(string $sql): Node;
}
