<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Bison\Directive;

use Override;
use SqlFaker\Compiler\Bison\Ast\BisonDeclaration;
use SqlFaker\Compiler\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\Compiler\Bison\Lexer\BisonLexeme;
use SqlFaker\Compiler\Bison\Lexer\BisonTokenStream;

/**
 * Reads `%expect`, the count of shift/reduce conflicts the author accepts.
 *
 * @visibility root
 */
final class ExpectDirectiveReader implements BisonDirectiveReader
{
    /**
     * Reports whether the directive is `%expect`.
     *
     * @param string $directive Directive name including its percent sign
     *
     * @return bool True for "%expect"
     */
    #[Override]
    public function handles(string $directive): bool
    {
        return $directive === '%expect';
    }

    /**
     * Consumes the conflict count.
     *
     * @param BisonTokenStream $stream Stream positioned just after the directive name
     * @param string $directive Directive name including its percent sign
     *
     * @return BisonDeclaration|null The declaration, or null when no number follows
     */
    #[Override]
    public function read(BisonTokenStream $stream, string $directive): ?BisonDeclaration
    {
        unset($directive);

        $count = $stream->nextIf(BisonLexeme::Number)?->asInt();

        return $count === null ? null : new BisonExpectDeclaration($count);
    }
}
