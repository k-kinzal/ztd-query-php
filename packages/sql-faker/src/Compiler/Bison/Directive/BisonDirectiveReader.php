<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Directive;

use SqlFaker\Compiler\Bison\Ast\BisonDeclaration;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;

/**
 * Reads the arguments of one kind of Bison declaration.
 *
 * The eight declaration kinds share a spelling — a percent sign and a name —
 * and nothing else: `%expect` takes a number, `%token` takes a list of names
 * with optional codes and aliases, `%parse-param` takes a block of host code.
 * Each shape is its own reader so that it can be exercised against the tokens
 * it is meant to read, rather than through a whole grammar file.
 *
 * @visibility root
 */
interface BisonDirectiveReader
{
    /**
     * Reports whether this reader knows the directive.
     *
     * @param string $directive Directive name including its percent sign, e.g. "%token"
     *
     * @return bool True when this reader should consume the arguments
     */
    public function handles(string $directive): bool;

    /**
     * Consumes the directive's arguments from the stream.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when the arguments were not the expected shape
     */
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration;
}
